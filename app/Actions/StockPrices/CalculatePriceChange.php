<?php

namespace App\Actions\StockPrices;

use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CalculatePriceChange
{
    /**
     * Calculate price changes between two dates for multiple stocks
     *
     * @param  array  $symbols  Array of validated stock symbols
     * @param  string  $startDate  Start date in Y-m-d format
     * @param  string  $endDate  End date in Y-m-d format
     */
    public function handle(array $symbols, string $startDate, string $endDate): JsonResponse
    {
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

        $startDatePrices = $this->getPricesForDate($stockIds, $parsedStartDate);
        $endDatePrices = $this->getPricesForDate($stockIds, $parsedEndDate);

        $result = $this->calculateChanges($stocks, $startDatePrices, $endDatePrices, $parsedStartDate, $parsedEndDate);

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
    }

    /**
     * Get prices for a specific date for multiple stocks
     *
     * @param  array  $stockIds  Array of stock IDs
     * @param  string  $date  Date in Y-m-d format
     * @return array Prices indexed by stock_id
     */
    private function getPricesForDate(array $stockIds, string $date): array
    {
        return StockPrice::whereIn('stock_id', $stockIds)
            ->whereDate('price_date', $date)
            ->orderBy('price_timestamp', 'desc')
            ->get()
            ->groupBy('stock_id')
            ->map(function ($prices) {
                return $prices->first();
            })
            ->all();
    }

    /**
     * Calculate price changes between start and end dates
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $stocks  Collection of stocks
     * @param  array  $startDatePrices  Prices at start date
     * @param  array  $endDatePrices  Prices at end date
     * @param  string  $parsedStartDate  Formatted start date
     * @param  string  $parsedEndDate  Formatted end date
     * @return array Array of price change results
     */
    private function calculateChanges($stocks, array $startDatePrices, array $endDatePrices, string $parsedStartDate, string $parsedEndDate): array
    {
        $result = [];

        foreach ($stocks as $stock) {
            // Check if the stock id exists in both price arrays
            if (! isset($startDatePrices[$stock->id]) || ! isset($endDatePrices[$stock->id])) {
                continue;
            }

            $startPrice = $startDatePrices[$stock->id]->close ?? null;
            $endPrice = $endDatePrices[$stock->id]->close ?? null;

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

        return $result;
    }
}
