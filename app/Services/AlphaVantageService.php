<?php

namespace App\Services;

use App\Mail\ApiErrorNotification;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class AlphaVantageService
{
    protected $apiKey;

    protected $baseUrl = 'https://www.alphavantage.co/query';

    public function __construct()
    {
        $this->apiKey = config('services.alpha_vantage.key');
    }

    /**
     * Get the current stock price for a symbol
     *
     * @param  string  $symbol  The stock symbol to lookup
     * @return array|null Price data or null on error
     */
    public function getStockPrice($symbol)
    {
        try {
            // Alpha Vantage free tier allows 25 requests per day
            $rateLimiterKey = 'alpha-vantage-api';

            if (RateLimiter::tooManyAttempts($rateLimiterKey, 25)) {
                $seconds = RateLimiter::availableIn($rateLimiterKey);
                $errorMessage = "Daily rate limit exceeded for Alpha Vantage API. Try again in {$seconds} seconds.";

                Log::warning($errorMessage);
                $this->sendErrorNotification($errorMessage, 'Rate Limit', ['symbol' => $symbol]);

                return null;
            }

            RateLimiter::hit($rateLimiterKey, 86400);

            $response = Http::retry(2, 1000)->get($this->baseUrl, [
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
                    $errorMessage = "Alpha Vantage API error: {$data['Error Message']}";

                    Log::error($errorMessage);
                    $this->sendErrorNotification($data['Error Message'], 'API Response Error', [
                        'symbol' => $symbol,
                        'response' => array_slice($data, 0, 3), // Include part of the response
                    ]);

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

            $errorMessage = "Failed to fetch data from Alpha Vantage: {$response->status()}";

            Log::error($errorMessage);
            $this->sendErrorNotification($errorMessage, 'HTTP Error', [
                'symbol' => $symbol,
                'status' => $response->status(),
                'response' => $response->body() ? substr($response->body(), 0, 200) : null,
            ]);

            return null;
        } catch (Exception $e) {
            $errorMessage = "Alpha Vantage API exception: {$e->getMessage()}";

            Log::error($errorMessage);
            $this->sendErrorNotification($e->getMessage(), 'Exception', [
                'symbol' => $symbol,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Send an error notification email
     *
     * @param  string  $errorMessage  The error message
     * @param  string  $source  Specific source of the error
     * @param  array  $context  Additional context for the error
     */
    protected function sendErrorNotification(string $errorMessage, string $source, array $context = []): void
    {
        try {
            // Get admin email from config, fallback to a default if not set
            $recipient = config('mail.admin_email', config('mail.from.address'));

            if ($recipient) {
                Mail::to($recipient)
                    ->queue(new ApiErrorNotification(
                        $errorMessage,
                        "AlphaVantage API: {$source}",
                        $context
                    ));
            }
        } catch (Exception $e) {
            Log::error("Failed to send error notification email: {$e->getMessage()}");
        }
    }
}
