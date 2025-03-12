<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPrice extends Model
{
    protected $fillable = [
        'stock_id',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'price_timestamp',
    ];

    protected $casts = [
        'price_timestamp' => 'datetime',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
