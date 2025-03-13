<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
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

    public function getSingleLatest(Stock $stock)
    {
        return Cache::remember("stock.{$stock->symbol}.latest", self::CACHE_TTL, function () use ($stock) {
            return new StockPriceResource($stock);
        });
    }

    public function getMultipleLatest() {}

    public function calculatePriceChange() {}
}
