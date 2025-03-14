<?php

use App\Services\AlphaVantageService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Config::set('services.alpha_vantage.key', 'fake-api-key');
});

it('gets stock price when API responds successfully', function () {
    // Mock HTTP response
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response([
            'Time Series (1min)' => [
                '2025-03-14 09:00:00' => [
                    '1. open' => '150.00',
                    '2. high' => '151.50',
                    '3. low' => '149.00',
                    '4. close' => '151.00',
                    '5. volume' => '10000',
                ],
            ],
        ], 200),
    ]);

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('symbol')
        ->and($result['symbol'])->toBe('AAPL')
        ->and($result)->toHaveKey('timestamp')
        ->and($result)->toHaveKey('open')
        ->and($result['open'])->toBe('150.00')
        ->and($result)->toHaveKey('high')
        ->and($result['high'])->toBe('151.50')
        ->and($result)->toHaveKey('low')
        ->and($result['low'])->toBe('149.00')
        ->and($result)->toHaveKey('close')
        ->and($result['close'])->toBe('151.00')
        ->and($result)->toHaveKey('volume')
        ->and($result['volume'])->toBe('10000');

    // Verify the request was made with the correct parameters
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'https://www.alphavantage.co/query') &&
            $request['function'] === 'TIME_SERIES_INTRADAY' &&
            $request['symbol'] === 'AAPL' &&
            $request['interval'] === '1min' &&
            $request['apikey'] === 'fake-api-key';
    });
});

it('returns null when API response has an error message', function () {
    // Mock HTTP response with an error message
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response([
            'Error Message' => 'Invalid API call. Please retry or visit the documentation.',
        ], 200),
    ]);

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('INVALID');

    expect($result)->toBeNull();
});

it('returns null when API response has no time series data', function () {
    // Mock HTTP response with no time series data
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response([
            'Meta Data' => [
                '1. Information' => 'Intraday (1min) open, high, low, close prices and volume',
                '2. Symbol' => 'AAPL',
            ],
            // No Time Series data
        ], 200),
    ]);

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
});

it('returns null when API request fails', function () {
    // Mock HTTP response with a failure status
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response(null, 500),
    ]);

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
});

it('returns null when an exception occurs during API request', function () {
    // Mock HTTP to throw an exception
    Http::fake(function () {
        throw new \Exception('Connection error');
    });

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
});
