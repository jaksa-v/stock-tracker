<?php

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Stock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbols = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'META', 'TSLA', 'NVDA', 'AMD'];
        $names = [
            'Apple Inc.', 
            'Microsoft Corporation', 
            'Alphabet Inc.', 
            'Amazon.com, Inc.', 
            'Meta Platforms, Inc.', 
            'Tesla, Inc.',
            'NVIDIA Corporation',
            'Advanced Micro Devices, Inc.'
        ];
        
        $index = array_rand($symbols);
        
        return [
            'symbol' => $this->faker->unique()->randomElement($symbols),
            'name' => $names[$index],
            'description' => $this->faker->paragraph(),
        ];
    }
}
