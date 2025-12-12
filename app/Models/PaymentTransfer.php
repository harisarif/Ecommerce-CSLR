<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransfer extends Model
{
    protected $fillable = [
        'order_id',
        'shop_id',
        'stripe_transfer_id',
        'amount_cents',
        'platform_fee_cents',
        'currency',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
