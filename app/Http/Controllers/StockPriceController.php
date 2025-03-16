<?php

namespace App\Http\Controllers;

use App\Actions\StockPrices\CalculatePriceChange;
use App\Actions\StockPrices\GetMultipleLatestPrices;
use App\Http\Requests\CalculatePriceChangeRequest;
use App\Http\Requests\GetMultipleLatestStocksRequest;
use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
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
    public function getMultipleLatest(
        GetMultipleLatestStocksRequest $request,
        GetMultipleLatestPrices $getMultipleAction
    ) {
        $symbols = $request->getValidatedSymbols();

        if (empty($symbols)) {
            return response()->json([
                'error' => 'No valid stock symbols provided',
                'message' => 'Please provide at least one valid stock symbol',
            ], 400);
        }

        // Sort the symbols for cache key (for potential reusability)
        $sortedSymbols = collect($symbols)->sort()->implode('.');
        $cacheKey = "stocks.multiple.{$sortedSymbols}.latest";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($getMultipleAction, $symbols) {
            return $getMultipleAction->handle($symbols);
        });
    }

    /**
     * Calculate price changes between two dates for multiple stocks
     */
    public function calculatePriceChange(
        CalculatePriceChangeRequest $request,
        CalculatePriceChange $calculateAction
    ): JsonResponse {
        $symbols = $request->getValidatedSymbols();
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');

        if (empty($symbols)) {
            return response()->json([
                'error' => 'No valid stock symbols provided',
                'message' => 'Please provide at least one valid stock symbol',
            ], 400);
        }

        // Sort the symbols for cache key (for potential reusability)
        $sortedSymbols = collect($symbols)->sort()->implode('.');
        $cacheKey = "stocks.change.{$sortedSymbols}.{$startDate}.{$endDate}";

        return Cache::remember($cacheKey, self::CACHE_TTL_CHANGE, function () use ($calculateAction, $symbols, $startDate, $endDate) {
            return $calculateAction->handle($symbols, $startDate, $endDate);
        });
    }
}
