# Stock Tracker Documentation

## Introduction

Hello there!

-   This
-   is
-   awesome!

## Some Code

```php
Route::get('/', function () {
    return view('markdown.docs');
});
```

## Some More Code

```php
/**
 * Display a listing of the resource.
 *
 * @return \Illuminate\Http\Resources\Json\JsonResource|\Illuminate\Http\JsonResponse
 */
public function index()
{
    return Cache::remember('stocks.all', self::CACHE_TTL, function () {
        return StockResource::collection(Stock::all());
    });
}
```

## Conclusion

Thank you for your time!
