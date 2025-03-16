# Stock Tracker Documentation

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

Bazu sam organizovao u dvije tabele, **stocks** i **stock_prices**, koje su definisane sljedećim migracijama:

```php
Schema::create('stocks', function (Blueprint $table) {
	$table->id();
	$table->string('symbol', 10)->unique();
	$table->string('name');
	$table->text('description')->nullable();
	$table->timestamps();
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

Dodao sam određene indexe radi boljih performansi.

## Alpha Vantage API integracija

Funkcionalnost integracije sa Alpha Vantage API-jem sam implementirao kao odvojenu klasu u _app/Services/AlphaVantageService.php_ radi bolje organizacije koda, razdvajanja funkcionalnosti i lakšeg testiranja.

### getStockPrice()

Primijetio sam da AlphaVantage zna vratiti response sa statusom 200 i pored toga što je došlo do greške, ili makar nismo dobili podatke, pa odatle malo opširniji kod za handlovanje grešaka. Osim toga, upotrijebio sam _RateLimiter_ imajući u uvid ograničenje free tiera na 25 poziva dnevno.

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
return [
		'symbol' => $symbol,
		'timestamp' => $latestTimestamp,
		'open' => $latestData['1. open'],
		'high' => $latestData['2. high'],
		'low' => $latestData['3. low'],
		'close' => $latestData['4. close'],
		'volume' => $latestData['5. volume'],
];
```

### sendErrorNotification()

Takođe, postoji funkcija koja ce poslati mejl svaki put kada dodje do greške pri povlačenju podataka. Napravio sam potrebnu Mailable klasu i template i naznačio da se mail šalje preko Queue-a.

## Automatizacija

Za svrhe dobijanja podataka sa AlphaVantage-a napravio sam komandu **FetchStockPrices** i u **console.php** sam zakazao da se pokreće svakog minuta.

S obzirom na rate limiting, rijetko sam palio _Queue_ već sam komandu pokretao manuelno:

```bash
php artisan stocks:fetch
```

U sklopu komande, osim sto čuvam nove cijene za konkretnu akciju u bazi, popunjavam _Cache_ sa najaktuelnijom cijenom, kako za konkretnu akciju, tako i za listu svih akcija:

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

API endpointe same aplikacije organizovao sam na sljedeći način:

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

Prefix **/api** se podrazumijeva na svakom endpointu, i simboli su case insensitive.

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

Dakle, vraćam samo najpotrebnije podatke, a za StockPrice vraćam samo najnoviju cijenu (i samo _close_ vrijednost).

## Kontroleri

**StockController** je jednostavan i vraća podatke o akcijama ili konkretnoj akciji. Sve je cache-ovano na jedan dan jer se ovi podaci gotovo nikad ne mijenjaju.

**StockPriceController** je kontroler koji vraća podatke o cijenama akcija. Sve je cache-ovano na jedan minut, osim endpointa za kalkulaciju promjene cijene koja je na jedan dan. Validacija inputa (query parametara) se nalazi u Request-ovima gdje je to bilo potrebno, a komplikovaniju logiku sam izdvojio u app/Actions/StockPrices direktorijum.

## Napomene

-   Projekat je deployovan na **Laravel Cloud**-u.
-   Sve do sad opisane funcionalnosti su pokrivene testovima. Koristio sam **Pest**.
-   Pri setup-u se seed-uju podaci u **stocks** tabeli za akcije koje sam izabrao
-   u **StockPrice** modelu je implementirana funkcionalnost za automatsko popunjavanje **price_date**. To polje koristimo radi lakšeg prisupa kada nam trebaju raniji datumi, ne samo najnovije cijene

## Development

Koristio sam Laravel 12.

Treba klonirati projekat, instalirati requiremente sa Composer-om i Node-om, uraditi migracije i seed-ovati bazu:

```bash
git clone git@github.com:jaksa-v/stock-tracker.git

cd stock-tracker

composer install

npm install

php artisan migrate --seed

composer run dev
```

Obratiti pažnju na _.env.example_ fajl i dodati varijable koje nedostaju.

U developmentu se koristi SQLite.
