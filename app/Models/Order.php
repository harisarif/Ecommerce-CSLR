<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'buyer_id',
        'buyer_type',
        'price_subtotal',
        'price_vat',
        'price_shipping',
        'price_total',
        'price_currency',
        'coupon_code',
        'coupon_products',
        'coupon_discount',
        'coupon_discount_rate',
        'coupon_seller_id',
        'status',
        'transaction_fee_rate',
        'transaction_fee',
        'global_taxes_data',
        'payment_method',
        'payment_status',
        'shipping',
        'affiliate_data',
        'updated_at',
        'created_at',
        'trustap_transaction_id',
        'trustap_status'
    ];

    protected $casts = [
        'order_number' => 'integer',
        'buyer_id' => 'integer',
        'price_vat' => 'float',
        'coupon_discount' => 'float',
        'coupon_discount_rate' => 'integer',
        'coupon_seller_id' => 'integer',
        'status' => 'boolean',
        'transaction_fee_rate' => 'float',
        'transaction_fee' => 'float',
        'price_subtotal' => 'string',
        'price_shipping' => 'string',
        'price_total' => 'string',
        'price_currency' => 'string',
        'coupon_code' => 'string',
        'coupon_products' => 'string',
        'global_taxes_data' => 'string',
        'payment_method' => 'string',
        'payment_status' => 'string',
        'shipping' => 'string',
        'affiliate_data' => 'string',
        'buyer_type' => 'string',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
