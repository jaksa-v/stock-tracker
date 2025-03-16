<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestPrice = $this->prices->first();

        return [
            'symbol' => $this->symbol,
            'name' => $this->name,
            'price' => $latestPrice ? $latestPrice->close : null,
            'price_timestamp' => $latestPrice ? $latestPrice->price_timestamp->format('Y-m-d H:i:s') : null,
        ];
    }
}
