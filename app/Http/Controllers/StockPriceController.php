<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StockPriceController extends Controller
{
    /**
     * Cache time in seconds (1 min)
     */
    private const CACHE_TTL = 60;

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

        // Sort the symbols for cache key (for potential reusability)
        $sortedSymbols = collect($symbols)->sort()->implode('.');
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

    public function calculatePriceChange() {}
}
