<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'price_date',
        'price_timestamp',
    ];

    protected $casts = [
        'price_timestamp' => 'datetime',
        'price_date' => 'date',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($stockPrice) {
            // Auto-populate price_date from price_timestamp if not set
            if (empty($stockPrice->price_date) && ! empty($stockPrice->price_timestamp)) {
                $stockPrice->price_date = Carbon::parse($stockPrice->price_timestamp)->toDateString();
            }
        });
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
