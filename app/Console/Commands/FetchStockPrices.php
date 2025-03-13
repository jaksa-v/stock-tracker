<?php

namespace App\Console\Commands;

use App\Http\Resources\StockPriceResource;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Services\AlphaVantageService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchStockPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stocks:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch stock prices from Alpha Vantage';

    protected $alphaVantageService;

    public function __construct(AlphaVantageService $alphaVantageService)
    {
        parent::__construct();
        $this->alphaVantageService = $alphaVantageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stocks = Stock::all();

        if ($stocks->isEmpty()) {
            $this->info('No stocks found.');

            return 0;
        }

        $this->info('Fetching stock prices for '.$stocks->count().' stocks...');

        foreach ($stocks as $stock) {
            $this->info("Processing {$stock->symbol}...");

            try {
                $priceData = $this->alphaVantageService->getStockPrice($stock->symbol);

                if (! $priceData) {
                    $this->warn("Failed to fetch stock price for {$stock->symbol}");

                    continue;
                }

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

                $stockWithPrice = Stock::with(['prices' => function ($query) {
                    $query->latest('price_timestamp')->limit(1);
                }])->find($stock->id);

                Cache::put("stock.{$stock->symbol}.latest", new StockPriceResource($stockWithPrice), now()->addMinutes(1));

                $this->info("Successfully updated price for {$stock->symbol}");

            } catch (Exception $e) {
                $this->error("Error processing {$stock->symbol}: {$e->getMessage()}");
                Log::error("Stock fetch error for {$stock->symbol}: {$e->getMessage()}");
            }
        }

        $stocks = Stock::with(['prices' => function ($query) {
            $query->latest('price_timestamp')->limit(1);
        }])->get();

        $latestPrices = StockPriceResource::collection($stocks);

        Cache::put('stocks.all.latest', $latestPrices, now()->addMinutes(1));

        $this->info('Finished fetching stock prices');

        return 0;
    }
}
