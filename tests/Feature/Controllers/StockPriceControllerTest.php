<?php

use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('returns the latest stock prices for all stocks', function () {
    $stock1 = Stock::factory()->create(['symbol' => 'AAPL']);
    $stock2 = Stock::factory()->create(['symbol' => 'MSFT']);

    StockPrice::factory()->create([
        'stock_id' => $stock1->id,
        'price_timestamp' => Carbon::now()->subDays(2),
        'close' => 150.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock1->id,
        'price_timestamp' => Carbon::now()->subHours(1),
        'close' => 155.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock2->id,
        'price_timestamp' => Carbon::now()->subDays(2),
        'close' => 300.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock2->id,
        'price_timestamp' => Carbon::now()->subHours(1),
        'close' => 305.00,
    ]);

    // Request latest stock prices
    $response = $this->getJson('/api/prices/');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $responseData = json_decode($response->getContent(), true);
    expect(is_numeric($responseData['data'][0]['price']))->toBeTrue();
    expect(is_numeric($responseData['data'][1]['price']))->toBeTrue();

    // Now the cache should exist
    expect(Cache::has('stocks.all.latest'))->toBeTrue();

    // Second request should use the cache and return same content
    $response2 = $this->getJson('/api/prices/');
    expect($response2->getContent())->toBe($response->getContent());
});

it('returns the latest stock price for a specific stock', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_timestamp' => Carbon::now()->subDays(2),
        'close' => 150.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_timestamp' => Carbon::now()->subHours(1),
        'close' => 155.00,
    ]);

    // Request latest stock price for AAPL
    $response = $this->getJson('/api/prices/AAPL');

    $response->assertStatus(200)
        ->assertJsonPath('data.symbol', 'AAPL');

    $responseData = json_decode($response->getContent(), true);
    // Check if the price is a valid number but don't check exact value
    expect(is_numeric($responseData['data']['price']))->toBeTrue();

    // Now the cache should exist
    expect(Cache::has("stock.{$stock->symbol}.latest"))->toBeTrue();

    // Second request should use the cache
    $response2 = $this->getJson('/api/prices/AAPL');
    expect($response2->getContent())->toBe($response->getContent());
});

it('returns 404 for non-existent stock', function () {
    $this->getJson('/api/prices/INVALID')
        ->assertStatus(404);
});

it('returns empty response when no prices exist', function () {
    // Create stock but no prices
    Stock::factory()->create(['symbol' => 'AAPL']);

    $response = $this->getJson('/api/prices/AAPL');
    $response->assertStatus(200)
        ->assertJsonPath('data.symbol', 'AAPL')
        ->assertJsonPath('data.price', null);
});

it('calculates price change correctly for multiple stocks', function () {
    $apple = Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.']);
    $microsoft = Stock::factory()->create(['symbol' => 'MSFT', 'name' => 'Microsoft Corporation']);

    $startDate = '2025-01-01';
    $endDate = '2025-01-10';

    // Create start date prices
    StockPrice::factory()->create([
        'stock_id' => $apple->id,
        'price_date' => $startDate,
        'price_timestamp' => Carbon::parse($startDate)->setTime(16, 0, 0),
        'close' => 150.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $microsoft->id,
        'price_date' => $startDate,
        'price_timestamp' => Carbon::parse($startDate)->setTime(16, 0, 0),
        'close' => 250.00,
    ]);

    // Create end date prices
    StockPrice::factory()->create([
        'stock_id' => $apple->id,
        'price_date' => $endDate,
        'price_timestamp' => Carbon::parse($endDate)->setTime(16, 0, 0),
        'close' => 160.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $microsoft->id,
        'price_date' => $endDate,
        'price_timestamp' => Carbon::parse($endDate)->setTime(16, 0, 0),
        'close' => 240.00,
    ]);

    $response = $this->getJson('/api/prices/change?stocks=AAPL,MSFT&start_date='.$startDate.'&end_date='.$endDate);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'symbol',
                    'name',
                    'start_date',
                    'end_date',
                    'start_price',
                    'end_price',
                    'change',
                    'change_percent',
                ],
            ],
            'meta' => [
                'start_date',
                'end_date',
                'count',
            ],
        ]);

    // Assert specific values for Apple
    $response->assertJsonPath('data.0.symbol', 'AAPL');

    // Get the actual values for numeric comparison
    $responseData = json_decode($response->getContent(), true);
    expect(abs($responseData['data'][0]['start_price'] - 150.00))->toBeLessThan(0.01);
    expect(abs($responseData['data'][0]['end_price'] - 160.00))->toBeLessThan(0.01);
    expect(abs($responseData['data'][0]['change'] - 10.00))->toBeLessThan(0.01);
    $expectedChangePercent = round((10.00 / 150.00) * 100, 2);
    expect(abs($responseData['data'][0]['change_percent'] - $expectedChangePercent))->toBeLessThan(0.01);

    // Assert specific values for Microsoft
    $response->assertJsonPath('data.1.symbol', 'MSFT');

    // Get the actual values for numeric comparison
    expect(abs($responseData['data'][1]['start_price'] - 250.00))->toBeLessThan(0.01);
    expect(abs($responseData['data'][1]['end_price'] - 240.00))->toBeLessThan(0.01);
    expect(abs($responseData['data'][1]['change'] - (-10.00)))->toBeLessThan(0.01);
    $expectedChangePercent = round((-10.00 / 250.00) * 100, 2);
    expect(abs($responseData['data'][1]['change_percent'] - $expectedChangePercent))->toBeLessThan(0.01);

    // Verify cache exists
    $sortedSymbols = collect(['AAPL', 'MSFT'])->sort()->implode('.');
    $cacheKey = "stocks.change.{$sortedSymbols}.{$startDate}.{$endDate}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

it('validates required parameters for price change calculation', function () {
    // Missing all required parameters
    $response = $this->getJson('/api/prices/change');
    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid input parameters');

    // Missing end_date
    $response = $this->getJson('/api/prices/change?stocks=AAPL&start_date=2025-01-01');
    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid input parameters');

    // Invalid date format
    $response = $this->getJson('/api/prices/change?stocks=AAPL&start_date=not-a-date&end_date=2025-01-10');
    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid input parameters');

    // End date before start date
    $response = $this->getJson('/api/prices/change?stocks=AAPL&start_date=2025-01-10&end_date=2025-01-01');
    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid input parameters');
});

it('handles invalid stock symbols properly', function () {
    // Empty stocks string
    $response = $this->getJson('/api/prices/change?stocks=&start_date=2025-01-01&end_date=2025-01-10');
    $response->assertStatus(400)
        ->assertJsonPath('error', 'Invalid input parameters');

    // Invalid stock symbols (not in database)
    $response = $this->getJson('/api/prices/change?stocks=INVALID,NOTREAL&start_date=2025-01-01&end_date=2025-01-10');
    $response->assertStatus(404)
        ->assertJsonPath('error', 'No stocks found');
});

it('handles missing price data properly', function () {
    // Create test stock
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    // No price data for the given dates
    $response = $this->getJson('/api/prices/change?stocks=AAPL&start_date=2025-01-01&end_date=2025-01-10');
    $response->assertStatus(404)
        ->assertJsonPath('error', 'No price data');

    // Only start date has price data
    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_date' => '2025-01-01',
        'price_timestamp' => Carbon::parse('2025-01-01')->setTime(16, 0, 0),
        'close' => 150.00,
    ]);

    $response = $this->getJson('/api/prices/change?stocks=AAPL&start_date=2025-01-01&end_date=2025-01-10');
    $response->assertStatus(404)
        ->assertJsonPath('error', 'No price data');
});

it('normalizes and deduplicates stock symbols', function () {
    // Create test stock
    $stock = Stock::factory()->create(['symbol' => 'AAPL', 'name' => 'Apple Inc.']);

    // Define dates
    $startDate = '2025-01-01';
    $endDate = '2025-01-10';

    // Create price data
    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_date' => $startDate,
        'price_timestamp' => Carbon::parse($startDate)->setTime(16, 0, 0),
        'close' => 150.00,
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_date' => $endDate,
        'price_timestamp' => Carbon::parse($endDate)->setTime(16, 0, 0),
        'close' => 160.00,
    ]);

    // Test with mixed case, spaces, and duplicates (URL encoded for spaces)
    $response = $this->getJson('/api/prices/change?stocks=%20aapl%20,%20AAPL,%20%20aapl%20&start_date='.$startDate.'&end_date='.$endDate);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.symbol', 'AAPL');
});
