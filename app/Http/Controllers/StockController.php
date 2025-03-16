<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Support\Facades\Cache;

class StockController extends Controller
{
    /**
     * Cache time in seconds (1 day)
     */
    private const CACHE_TTL = 86400;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource|\Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return Cache::remember('stocks.all', self::CACHE_TTL, function () {
            return StockResource::collection(Stock::all());
        });
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Resources\Json\JsonResource|\Illuminate\Http\JsonResponse
     */
    public function show($stockSymbol)
    {
        $symbol = strtoupper($stockSymbol);
        $cacheKey = 'stocks.'.$symbol;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($symbol) {
            $stock = Stock::where('symbol', $symbol)->first();

            if (! $stock) {
                return response()->json([
                    'error' => 'Stock not found',
                    'message' => "No stock found with symbol '{$symbol}'",
                ], 404);
            }

            return new StockResource($stock);
        });
    }
}
