<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;
    protected $fillable = [
        'symbol',
        'name',
        'description',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(StockPrice::class);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'symbol';
    }
}
