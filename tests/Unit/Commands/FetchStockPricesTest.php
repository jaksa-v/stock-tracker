<?php

use App\Console\Commands\FetchStockPrices;
use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use App\Services\AlphaVantageService;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

beforeEach(function () {
    Cache::flush();
});

it('fetches and stores prices for all stocks', function () {
    $stock1 = Stock::factory()->create(['symbol' => 'AAPL']);
    $stock2 = Stock::factory()->create(['symbol' => 'MSFT']);

    // Mock for AlphaVantageService
    $this->mock(AlphaVantageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStockPrice')
            ->with('AAPL')
            ->once()
            ->andReturn([
                'symbol' => 'AAPL',
                'timestamp' => '2025-03-14 09:00:00',
                'open' => '150.00',
                'high' => '151.50',
                'low' => '149.00',
                'close' => '151.00',
                'volume' => '10000',
            ]);

        $mock->shouldReceive('getStockPrice')
            ->with('MSFT')
            ->once()
            ->andReturn([
                'symbol' => 'MSFT',
                'timestamp' => '2025-03-14 09:00:00',
                'open' => '300.00',
                'high' => '302.50',
                'low' => '299.50',
                'close' => '301.75',
                'volume' => '8000',
            ]);
    });

    // Execute command
    $this->artisan(FetchStockPrices::class)
        ->expectsOutput('Fetching stock prices for 2 stocks...')
        ->expectsOutput('Processing AAPL...')
        ->expectsOutput('Successfully updated price for AAPL')
        ->expectsOutput('Processing MSFT...')
        ->expectsOutput('Successfully updated price for MSFT')
        ->expectsOutput('Finished fetching stock prices')
        ->assertExitCode(0);

    // Check that stock prices were created in the database
    $this->assertDatabaseHas('stock_prices', [
        'stock_id' => $stock1->id,
        'open' => '150.00',
        'high' => '151.50',
        'low' => '149.00',
        'close' => '151.00',
        'volume' => '10000',
    ]);

    $this->assertDatabaseHas('stock_prices', [
        'stock_id' => $stock2->id,
        'open' => '300.00',
        'high' => '302.50',
        'low' => '299.50',
        'close' => '301.75',
        'volume' => '8000',
    ]);

    // Check that cache was updated for individual stocks
    $cachedApple = Cache::get('stock.AAPL.latest');
    $cachedMicrosoft = Cache::get('stock.MSFT.latest');

    expect($cachedApple)->toBeInstanceOf(StockPriceResource::class);
    expect($cachedMicrosoft)->toBeInstanceOf(StockPriceResource::class);

    // Check that cache was updated for all stocks
    $cachedAll = Cache::get('stocks.all.latest');
    expect($cachedAll)->not->toBeNull();
    expect($cachedAll->collection->count())->toBe(2);
});

it('handles API failures gracefully', function () {
    // Create test stock
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    // Create mock for AlphaVantageService
    $this->mock(AlphaVantageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStockPrice')
            ->with('AAPL')
            ->once()
            ->andReturn(null);
    });

    // Execute command
    $this->artisan(FetchStockPrices::class)
        ->expectsOutput('Fetching stock prices for 1 stocks...')
        ->expectsOutput('Processing AAPL...')
        ->expectsOutput('Failed to fetch stock price for AAPL')
        ->expectsOutput('Finished fetching stock prices')
        ->assertExitCode(0);

    // Check that no stock prices were created
    $this->assertDatabaseCount('stock_prices', 0);
});

it('handles empty stock list', function () {
    // No stocks in database

    // Execute command
    $this->artisan(FetchStockPrices::class)
        ->expectsOutput('No stocks found.')
        ->assertExitCode(0);
});

it('handles exceptions during processing', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    // Mock for AlphaVantageService that throws an exception
    $this->mock(AlphaVantageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStockPrice')
            ->with('AAPL')
            ->once()
            ->andThrow(new Exception('API connection error'));
    });

    // Execute command
    $this->artisan(FetchStockPrices::class)
        ->expectsOutput('Fetching stock prices for 1 stocks...')
        ->expectsOutput('Processing AAPL...')
        ->expectsOutput('Error processing AAPL: API connection error')
        ->expectsOutput('Finished fetching stock prices')
        ->assertExitCode(0);

    // Check that no stock prices were created
    $this->assertDatabaseCount('stock_prices', 0);
});

it('updates the cache for the fetched stock prices', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    // Mock for AlphaVantageService
    $this->mock(AlphaVantageService::class, function (MockInterface $mock) {
        $mock->shouldReceive('getStockPrice')
            ->with('AAPL')
            ->once()
            ->andReturn([
                'symbol' => 'AAPL',
                'timestamp' => '2025-03-14 09:00:00',
                'open' => '150.00',
                'high' => '151.50',
                'low' => '149.00',
                'close' => '151.00',
                'volume' => '10000',
            ]);
    });

    // Execute command
    $this->artisan(FetchStockPrices::class)->assertExitCode(0);

    // Verify that individual stock cache was updated
    expect(Cache::has('stock.AAPL.latest'))->toBeTrue();

    // Verify that all stocks cache was updated
    expect(Cache::has('stocks.all.latest'))->toBeTrue();

    // Verify cache content for individual stock
    $cachedStock = Cache::get('stock.AAPL.latest');
    expect($cachedStock)->toBeInstanceOf(StockPriceResource::class);

    // Verify that cache exists with the correct data
    expect(Cache::has('stock.AAPL.latest'))->toBeTrue();
});
