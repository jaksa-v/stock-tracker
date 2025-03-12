<?php

namespace Database\Seeders;

use App\Models\Stock;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Stock::create([
            'symbol' => 'AAPL',
            'name' => 'Apple Inc.',
            'description' => 'Apple Inc. designs, develops, and sells consumer electronics, computer software, and online services.',
        ]);

        Stock::create([
            'symbol' => 'MSFT',
            'name' => 'Microsoft Corporation',
            'description' => 'Microsoft Corporation develops, licenses, and supports a range of software products, services, and devices.',
        ]);

        Stock::create([
            'symbol' => 'GOOGL',
            'name' => 'Alphabet Inc.',
            'description' => 'Alphabet Inc. is a holding company with Google as its main subsidiary, which focuses on search, cloud computing, and advertising.',
        ]);

        Stock::create([
            'symbol' => 'AMZN',
            'name' => 'Amazon.com, Inc.',
            'description' => 'Amazon.com Inc. is an online retailer and cloud computing company that provides a wide array of products and services.',
        ]);

        Stock::create([
            'symbol' => 'META',
            'name' => 'Meta Platforms, Inc.',
            'description' => 'Meta Platforms Inc. (formerly Facebook) develops and operates social media platforms and technologies.',
        ]);
    }
}
