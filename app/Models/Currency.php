<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'currency_format',
        'symbol_direction',
        'space_money_symbol',
        'exchange_rate',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'exchange_rate' => 'float',
    ];

    /**
     * Scope to get only active currency
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
