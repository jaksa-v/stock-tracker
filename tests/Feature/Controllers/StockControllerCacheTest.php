<?php

use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('caches the index response', function () {
    $stock1 = Stock::factory()->create(['symbol' => 'AAPL']);
    $stock2 = Stock::factory()->create(['symbol' => 'MSFT']);

    StockPrice::factory()->create([
        'stock_id' => $stock1->id,
        'price_timestamp' => Carbon::now(),
    ]);

    StockPrice::factory()->create([
        'stock_id' => $stock2->id,
        'price_timestamp' => Carbon::now(),
    ]);

    // First request should miss cache and store result
    $response1 = $this->getJson('/api/stocks');
    $response1->assertStatus(200);

    // Verify that cache has been set with the correct key
    expect(Cache::has('stocks.all'))->toBeTrue();

    // Modify a stock to confirm the next response comes from cache
    $originalName = $stock1->name;
    $stock1->update(['name' => 'Changed Name']);

    // Second request should hit cache and return the old data
    $response2 = $this->getJson('/api/stocks');
    $response2->assertStatus(200);

    // Response should still contain the original name, not the updated one
    $response2->assertJsonFragment(['name' => $originalName]);
    $response2->assertJsonMissing(['name' => 'Changed Name']);

    // Response content should be identical
    expect($response1->getContent())->toBe($response2->getContent());
});

it('caches individual stock responses', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_timestamp' => Carbon::now(),
    ]);

    // First request should miss cache and store result
    $response1 = $this->getJson("/api/stocks/{$stock->symbol}");
    $response1->assertStatus(200);

    // Verify that cache has been set with the correct key
    $cacheKey = "stocks.{$stock->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Modify the stock to confirm the next response comes from cache
    $originalName = $stock->name;
    $stock->update(['name' => 'Changed Name']);

    // Second request should hit cache and return the old data
    $response2 = $this->getJson("/api/stocks/{$stock->symbol}");
    $response2->assertStatus(200);

    // Response should still contain the original name, not the updated one
    $response2->assertJsonFragment(['name' => $originalName]);
    $response2->assertJsonMissing(['name' => 'Changed Name']);

    // Response content should be identical
    expect($response1->getContent())->toBe($response2->getContent());
});

it('updates cache for modified stock data', function () {
    $stock = Stock::factory()->create(['symbol' => 'AAPL']);

    StockPrice::factory()->create([
        'stock_id' => $stock->id,
        'price_timestamp' => Carbon::now(),
    ]);

    // Request to store in cache
    $this->getJson("/api/stocks/{$stock->symbol}");
    $this->getJson('/api/stocks');

    // Verify cache exists
    expect(Cache::has("stocks.{$stock->id}"))->toBeTrue();
    expect(Cache::has('stocks.all'))->toBeTrue();

    // Update the stock directly in the database
    $stock->update([
        'name' => 'Updated Name',
        'description' => 'Updated description',
    ]);

    // Manually clear the cache as we would in a controller
    Cache::forget("stocks.{$stock->id}");
    Cache::forget('stocks.all');

    // Verify cache is cleared
    expect(Cache::has("stocks.{$stock->id}"))->toBeFalse();
    expect(Cache::has('stocks.all'))->toBeFalse();

    // New request should return updated data and repopulate cache
    $response = $this->getJson("/api/stocks/{$stock->symbol}");
    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Updated Name']);

    // Verify cache has been repopulated
    expect(Cache::has("stocks.{$stock->id}"))->toBeTrue();
});
