<?php

use App\Mail\ApiErrorNotification;
use App\Services\AlphaVantageService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

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

it('returns null and sends error email when API response has an error message', function () {
    // Fake mail to catch notifications
    Mail::fake();
    
    // Mock HTTP response with an error message
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response([
            'Error Message' => 'Invalid API call. Please retry or visit the documentation.',
        ], 200),
    ]);

    // Configure admin email
    Config::set('mail.admin_email', 'admin@example.com');

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('INVALID');

    expect($result)->toBeNull();
    
    // Assert that an email was sent
    Mail::assertQueued(ApiErrorNotification::class, function ($mail) {
        return $mail->source === 'AlphaVantage API: API Response Error' &&
               $mail->errorMessage === 'Invalid API call. Please retry or visit the documentation.' &&
               $mail->context['symbol'] === 'INVALID' &&
               isset($mail->context['response']);
    });
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

it('returns null and sends error email when API request fails', function () {
    // Fake mail to catch notifications
    Mail::fake();
    
    // Override the isSuccessful method to always return false
    $mockResponse = new class {
        public function json() { return []; }
        public function successful() { return false; }
        public function status() { return 500; }
        public function body() { return '{"error":"Server error"}'; }
    };
    
    Http::shouldReceive('retry')
        ->once()
        ->andReturnSelf();
        
    Http::shouldReceive('get')
        ->once()
        ->andReturn($mockResponse);

    // Configure admin email
    Config::set('mail.admin_email', 'admin@example.com');

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
    
    // Assert that an email was sent
    Mail::assertQueued(ApiErrorNotification::class, function ($mail) {
        return $mail->source === 'AlphaVantage API: HTTP Error' &&
               str_contains($mail->errorMessage, 'Failed to fetch data from Alpha Vantage: 500') &&
               $mail->context['symbol'] === 'AAPL' &&
               $mail->context['status'] === 500;
    });
});

it('returns null and sends error email when an exception occurs during API request', function () {
    // Fake mail to catch notifications
    Mail::fake();
    
    // Mock HTTP to throw an exception
    Http::fake(function () {
        throw new \Exception('Connection error');
    });

    // Configure admin email
    Config::set('mail.admin_email', 'admin@example.com');

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
    
    // Assert that an email was sent
    Mail::assertQueued(ApiErrorNotification::class, function ($mail) {
        return $mail->source === 'AlphaVantage API: Exception' &&
               $mail->errorMessage === 'Connection error' &&
               $mail->context['symbol'] === 'AAPL';
    });
});

it('sends error email when rate limit is exceeded', function () {
    // Fake mail to catch notifications
    Mail::fake();
    
    // Configure admin email
    Config::set('mail.admin_email', 'admin@example.com');
    
    // Make the rate limiter report too many attempts
    Illuminate\Support\Facades\RateLimiter::shouldReceive('tooManyAttempts')
        ->once()
        ->andReturn(true);
    
    Illuminate\Support\Facades\RateLimiter::shouldReceive('availableIn')
        ->once()
        ->andReturn(3600); // 1 hour
    
    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('AAPL');

    expect($result)->toBeNull();
    
    // Assert that an email was sent
    Mail::assertQueued(ApiErrorNotification::class, function ($mail) {
        return $mail->source === 'AlphaVantage API: Rate Limit' &&
               str_contains($mail->errorMessage, 'Daily rate limit exceeded') &&
               $mail->context['symbol'] === 'AAPL';
    });
});

it('does not send email when admin email is not configured', function () {
    // Fake mail to catch notifications
    Mail::fake();
    
    // Set admin email to null
    Config::set('mail.admin_email', null);
    Config::set('mail.from.address', null);
    
    // Mock HTTP response with an error message
    Http::fake([
        'https://www.alphavantage.co/query*' => Http::response([
            'Error Message' => 'Invalid API call.',
        ], 200),
    ]);

    $service = app(AlphaVantageService::class);
    $result = $service->getStockPrice('INVALID');

    expect($result)->toBeNull();
    
    // Assert that no email was sent
    Mail::assertNothingQueued();
});
