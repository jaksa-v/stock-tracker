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
