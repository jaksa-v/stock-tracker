<?php

namespace App\Actions\StockPrices;

use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GetMultipleLatestPrices
{
    /**
     * Get latest prices for multiple stocks using array of symbols
     *
     * @param  array  $symbols  Array of validated stock symbols
     */
    public function handle(array $symbols): AnonymousResourceCollection|JsonResponse
    {
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
    }
}
