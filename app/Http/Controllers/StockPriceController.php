<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StockPriceController extends Controller
{
    /**
     * Cache time in seconds (1 min, 1 day)
     */
    private const CACHE_TTL = 60;

    private const CACHE_TTL_CHANGE = 86400;

    public function getAllLatest()
    {
        return Cache::remember('stocks.all.latest', self::CACHE_TTL, function () {
            return StockPriceResource::collection(
                Stock::with(['prices' => function ($query) {
                    $query->latest('price_timestamp')->limit(1);
                }])->get()
            );
        });
    }

    /**
     * Get the latest price for a single stock by symbol
     *
     * @param  string  $stockSymbol
     * @return \Illuminate\Http\Resources\Json\JsonResource|\Illuminate\Http\JsonResponse
     */
    public function getSingleLatest($stockSymbol)
    {
        $symbol = strtoupper(trim($stockSymbol));
        $cacheKey = "stock.{$symbol}.latest";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($symbol) {
            $stock = Stock::where('symbol', $symbol)->first();

            if (! $stock) {
                return response()->json([
                    'error' => 'Stock not found',
                    'message' => "No stock found with symbol '{$symbol}'",
                ], 404);
            }

            return new StockPriceResource($stock);
        });
    }

    /**
     * Get latest prices for multiple stocks using a comma-separated list of symbols
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
     */
    public function getMultipleLatest(Request $request)
    {
        $stocksString = $request->input('stocks');
        $validationResult = $this->validateStockSymbols($stocksString);

        if (! is_array($validationResult)) {
            return $validationResult; // Return error response
        }

        $symbols = $validationResult;

        // Sort the symbols for cache key (for potential reusability)
        $sortedSymbols = collect($validationResult)->sort()->implode('.');
        $cacheKey = "stocks.multiple.{$sortedSymbols}.latest";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($symbols) {
            $stocks = Stock::whereIn('symbol', $symbols)
                ->with(['prices' => function ($query) {
                    $query->latest('price_timestamp')->limit(1);
                }])
                ->get();

            if ($stocks->isEmpty()) {
                return response()->json([
                    'error' => 'No stocks found',
                    'message' => 'None of the requested symbols could be found in our database',
                ], 404);
            }

            return StockPriceResource::collection($stocks);
        });
    }

    /**
     * Calculate price changes between two dates for multiple stocks
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculatePriceChange(Request $request)
    {
        // Validate required inputs
        $validator = validator($request->all(), [
            'stocks' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid input parameters',
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $stocksString = $request->input('stocks');

        $validationResult = $this->validateStockSymbols($stocksString);

        if (! is_array($validationResult)) {
            return $validationResult; // Return error response
        }

        $symbols = $validationResult;

        // Sort the symbols for cache key (for potential reusability)
        $sortedSymbols = collect($symbols)->sort()->implode('.');
        $cacheKey = "stocks.change.{$sortedSymbols}.{$startDate}.{$endDate}";

        return Cache::remember($cacheKey, self::CACHE_TTL_CHANGE, function () use ($symbols, $startDate, $endDate) {
            $parsedStartDate = Carbon::parse($startDate)->toDateString();
            $parsedEndDate = Carbon::parse($endDate)->toDateString();

            $stocks = Stock::whereIn('symbol', $symbols)->get();

            if ($stocks->isEmpty()) {
                return response()->json([
                    'error' => 'No stocks found',
                    'message' => 'None of the requested symbols could be found in our database',
                ], 404);
            }

            $stockIds = $stocks->pluck('id')->toArray();

            $startDatePrices = StockPrice::whereIn('stock_id', $stockIds)
                ->whereDate('price_date', $parsedStartDate)
                ->orderBy('price_timestamp', 'desc')
                ->get()
                ->groupBy('stock_id')
                ->map(function ($prices) {
                    return $prices->first();
                });

            $endDatePrices = StockPrice::whereIn('stock_id', $stockIds)
                ->whereDate('price_date', $parsedEndDate)
                ->orderBy('price_timestamp', 'desc')
                ->get()
                ->groupBy('stock_id')
                ->map(function ($prices) {
                    return $prices->first();
                });

            $result = [];

            foreach ($stocks as $stock) {
                $startPrice = isset($startDatePrices[$stock->id]) ? $startDatePrices[$stock->id]->close : null;
                $endPrice = isset($endDatePrices[$stock->id]) ? $endDatePrices[$stock->id]->close : null;

                if ($startPrice === null || $endPrice === null) {
                    continue;
                }

                $change = $endPrice - $startPrice;
                $changePercent = ($startPrice > 0) ? ($change / $startPrice) * 100 : 0;

                $result[] = [
                    'symbol' => $stock->symbol,
                    'name' => $stock->name,
                    'start_date' => $parsedStartDate,
                    'end_date' => $parsedEndDate,
                    'start_price' => round($startPrice, 2),
                    'end_price' => round($endPrice, 2),
                    'change' => round($change, 2),
                    'change_percent' => round($changePercent, 2),
                ];
            }

            if (empty($result)) {
                return response()->json([
                    'error' => 'No price data',
                    'message' => 'Could not find price data for the requested stocks in the given date range',
                ], 404);
            }

            return response()->json([
                'data' => $result,
                'meta' => [
                    'start_date' => $parsedStartDate,
                    'end_date' => $parsedEndDate,
                    'count' => count($result),
                ],
            ]);
        });
    }

    /**
     * Validate stock symbols from a string input
     *
     * @param  string  $stocksString
     * @return array|\Illuminate\Http\JsonResponse Array of valid symbols or error response
     */
    private function validateStockSymbols($stocksString)
    {
        if (empty($stocksString) || ! is_string($stocksString)) {
            return response()->json([
                'error' => 'Please provide valid stock symbols',
                'message' => 'Use a comma-separated list (stocks=AAPL,MSFT,GOOG)',
            ], 400);
        }

        $symbols = explode(',', $stocksString);
        $symbols = collect($symbols)
            ->map(fn ($symbol) => trim(strtoupper($symbol)))
            ->filter(fn ($symbol) => ! empty($symbol))
            ->unique()
            ->values()
            ->toArray();

        if (empty($symbols)) {
            return response()->json([
                'error' => 'No valid stock symbols provided',
                'message' => 'Please provide at least one valid stock symbol',
            ], 400);
        }

        return $symbols;
    }
}
