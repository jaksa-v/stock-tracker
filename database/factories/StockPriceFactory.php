<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockPrice>
 */
class StockPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StockPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = $this->faker->randomFloat(2, 50, 1000);
        $high = $basePrice * (1 + $this->faker->randomFloat(2, 0.01, 0.05));
        $low = $basePrice * (1 - $this->faker->randomFloat(2, 0.01, 0.05));
        $close = $this->faker->randomFloat(2, $low, $high);
        
        return [
            'stock_id' => Stock::factory(),
            'open' => $basePrice,
            'high' => $high,
            'low' => $low,
            'close' => $close,
            'volume' => $this->faker->numberBetween(10000, 10000000),
            'price_timestamp' => Carbon::now()->subHours($this->faker->numberBetween(1, 24)),
        ];
    }
}
