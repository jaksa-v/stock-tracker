<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlphaVantageService
{
    protected $apiKey;

    protected $baseUrl = 'https://www.alphavantage.co/query';

    public function __construct()
    {
        $this->apiKey = config('services.alpha_vantage.key');
    }

    public function getStockPrice($symbol)
    {
        try {
            $response = Http::retry(3, 100)->get($this->baseUrl, [
                'function' => 'TIME_SERIES_INTRADAY',
                'symbol' => $symbol,
                'interval' => '1min',
                'apikey' => $this->apiKey,
                'outputsize' => 'compact',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Handle potential error responses from Alpha Vantage
                if (isset($data['Error Message'])) {
                    Log::error("Alpha Vantage API error: {$data['Error Message']}");

                    return null;
                }

                // Extract the most recent price data
                $timeSeries = $data['Time Series (1min)'] ?? [];
                if (empty($timeSeries)) {
                    return null;
                }

                // Get the most recent data point
                $latestTimestamp = array_key_first($timeSeries);
                $latestData = $timeSeries[$latestTimestamp];

                return [
                    'symbol' => $symbol,
                    'timestamp' => $latestTimestamp,
                    'open' => $latestData['1. open'],
                    'high' => $latestData['2. high'],
                    'low' => $latestData['3. low'],
                    'close' => $latestData['4. close'],
                    'volume' => $latestData['5. volume'],
                ];
            }

            Log::error("Failed to fetch data from Alpha Vantage: {$response->status()}");

            return null;
        } catch (Exception $e) {
            Log::error("Alpha Vantage API exception: {$e->getMessage()}");

            return null;
        }
    }
}
