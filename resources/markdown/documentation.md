## Uvod

U ovom dokumentu ću proći kroz sve komponente mog rješenja za zadatak koji sam dobio u sklopu invtervjua.

## Dizajn baze podataka

Prolazeći kroz dokumentaciju, s obzirom na to da će biti potrebno da pravim automatizaciju koja vuče podatke svakog minuta, pretpostavio sam da mi je potreban TIME_SERIES_INTRADAY API sa intervalom 1 min.

Vidio sam da vraća podatke u ovom obliku:

```json
{
    "1. open": "248.2300",
    "2. high": "248.2400",
    "3. low": "248.0100",
    "4. close": "248.2400",
    "5. volume": "24"
}
```

Bazu sam organizovao u dvije tabele, **stocks** i **stock_prices**, koje su definisane sljedecim migracijama:

```php
Schema::create('stocks', function (Blueprint $table) {
	$table->id();
	$table->string('symbol', 10)->unique();
	$table->string('name');
	$table->text('description')->nullable();
	$table->timestamps();

	$table->index('symbol');
});
```

```php
Schema::create('stock_prices', function (Blueprint $table) {
	$table->id();
	$table->foreignId('stock_id')->constrained()->onDelete('cascade');
	$table->decimal('open', 10, 4);
	$table->decimal('high', 10, 4);
	$table->decimal('low', 10, 4);
	$table->decimal('close', 10, 4);
	$table->bigInteger('volume');
	$table->date('price_date');
	$table->timestamp('price_timestamp')->useCurrent();
	$table->timestamps();

	// Add indexes for performance
	$table->index('price_timestamp');
	$table->index('price_date');
	$table->index(['stock_id', 'price_timestamp']);
	$table->index(['stock_id', 'price_date']);
});
```

Dodao sam odredjene indexe radi boljih performansi.

## Alpha Vantage API integracija

Funkcionalnost integracije sa Alpha Vantage API-jem sam implementirao kao odvojenu klasu u _app/Services/AlphaVantageService.php_ radi bolje organizacije koda, razdvajanja funkcionalnosti i lakseg testiranja.

### getStockPrice()

Primijetio sam da AlphaVantage zna vratiti response sa statusom 200 i pored toga sto je doslo do greske, ili makar nismo dobili podatke, pa odatle malo opsirniji kod za handlovanje gresaka. Osim toga, upotrijebio sam RateLimiter imajuci u uvid ogranicenje free tiera na 25 poziva dnevno.

```php

// zahtjev
$response = Http::retry(2, 1000)->get($this->baseUrl, [
	'function' => 'TIME_SERIES_INTRADAY',
	'symbol' => $symbol,
	'interval' => '1min',
	'apikey' => $this->apiKey,
	'outputsize' => 'compact',
]);

// oblik u kom vracamo podatke
$response = Http::retry(2, 1000)->get($this->baseUrl, [
	'function' => 'TIME_SERIES_INTRADAY',
	'symbol' => $symbol,
	'interval' => '1min',
	'apikey' => $this->apiKey,
	'outputsize' => 'compact',
]);
```

### sendErrorNotification()

Takodje, postoji funkcija koja ce poslati mejl svaki put kada dodje do greske pri povlacenju fajlova. Napravio sam potrebnu Mailable klasu i template i naznacio da se mail salje preko Queue-a.

## Automatizacija

Za svrhe dobijanja podataka sa AlphaVantage-a napravio sam komandu **FetchStockPrices** i u **console.php** sam zakazao da se pokrece svakog minuta.

S obzirom na rate limiting, rijetko sam palio Queue vec sam komandu pokretao manuelno:

```bash
php artisan stocks:fetch
```

U sklopu komande, osim sto cuvam nove cijene za konkretnu akciju u bazu, popunjavam Cache sa najaktuelnijom cijenom, kako za konkretnu akciju, tako i za listu svih akcija:

```php
$stockPrice = new StockPrice([
	'stock_id' => $stock->id,
	'open' => $priceData['open'],
	'high' => $priceData['high'],
	'low' => $priceData['low'],
	'close' => $priceData['close'],
	'volume' => $priceData['volume'],
	'price_timestamp' => Carbon::parse($priceData['timestamp']),
]);

$stockPrice->save();

Cache::put("stock.{$stock->symbol}.latest", new StockPriceResource($stockWithPrice), now()->addMinutes(1));

Cache::put('stocks.all.latest', $latestPrices, now()->addMinutes(1));
```

## Postavka API endpoint-a

API endpointe same aplikacije organizovao sam na sljedeci nacin:

```php
Route::prefix('/stocks')->group(function () {
    Route::get('/', [StockController::class, 'index']);
    Route::get('/{stock}', [StockController::class, 'show']);
});

Route::prefix('/prices')->group(function () {
    Route::get('/', [StockPriceController::class, 'getAllLatest']);
    Route::get('/batch', [StockPriceController::class, 'getMultipleLatest']);
    Route::get('/change', [StockPriceController::class, 'calculatePriceChange']);
    Route::get('/{stock}', [StockPriceController::class, 'getSingleLatest']);
});
```

### Primjeri

-   **List Stocks** - /stocks
-   **Show Stock** - /stocks/AAPL
-   **List Latest Prices** - /prices
-   **Get Latest Price** - /prices/AAPL
-   **Get Batch Prices** - /prices/batch?stocks=AAPL,MSFT,AMZN
-   **Get Price Change** - /prices/change?stocks=AAPL,MSFT&start_date=2025-03-14&end_date=2025-03-15

Prefix /api se podrazumijeva na svakom endpointu, i simboli su case insensitive.

### Oblik podataka u Responsu

Za potrebe oblikovanja response-ova napravio sam resurse:

```php
// za Stocks
public function toArray(Request $request): array
{
	return [
		'symbol' => $this->symbol,
		'name' => $this->name,
		'description' => $this->description,
	];
}

// za StockPrice
public function toArray(Request $request): array
{
	$latestPrice = $this->prices->first();

	return [
		'symbol' => $this->symbol,
		'name' => $this->name,
		'price' => $latestPrice ? $latestPrice->close : null,
		'price_timestamp' => $latestPrice ? $latestPrice->price_timestamp : null,
	];
}
```

Dakle, vracam samo najpotrebnije podatke, a za StockPrice vracam samo najaktuelniju cijenu (_close_ vrijednost).

## Kontroleri

## Testovi

Sve do sad opisane funcionalnosti su pokrivene testovima. Koristio sam Pest.

## Development

Koristio sam Laravel 12.

## Deployment

Projekat je deployovan na Laravel Cloud-u.
