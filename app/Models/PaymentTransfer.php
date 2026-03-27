<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransfer extends Model
{
    protected $fillable = [
        'order_id',
        'shop_id',
        // Stripe
        'payment_intent_id',
        'charge_id',
        'stripe_transfer_id',

        'trustap_transaction_id',

        // Money
        'amount_cents',
        'platform_fee_cents',
        'currency',

        
        // ✅ Newly added fields (from migration)
        'checkout_amount_cents',
        'checkout_currency',

        'gross_amount_cents',
        'stripe_fee_cents',
        'net_amount_cents',
        'settlement_currency',
        'exchange_rate',

        // Hold logic
        'release_at',
        'status',

        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'release_at' => 'datetime',
    ];


    /**
    * Payment belongs to a shop (seller)
    */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Payment belongs to an order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Optional: Payment belongs to a buyer (user)
     * Only if you store buyer_id in meta or column later
     */
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
