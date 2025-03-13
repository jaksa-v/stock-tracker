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
     */
    public function index()
    {
        return Cache::remember('stocks.all', self::CACHE_TTL, function () {
            return StockResource::collection(Stock::all());
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Stock $stock)
    {
        $cacheKey = 'stocks.' . $stock->id;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stock) {
            return new StockResource($stock);
        });
    }
}
