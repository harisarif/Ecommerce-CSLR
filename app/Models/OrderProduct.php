<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $table = 'order_products';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'seller_id',
        'buyer_id',
        'buyer_type',
        'product_id',
        'product_type',
        'listing_type',
        'product_title',
        'product_slug',
        'product_unit_price',
        'product_quantity',
        'product_currency',
        'product_vat_rate',
        'product_vat',
        'product_total_price',
        'variation_option_ids',
        'commission_rate',
        'order_status',
        'is_approved',
        'shipping_tracking_number',
        'shipping_tracking_url',
        'shipping_method',
        'seller_shipping_cost',
        'updated_at',
        'created_at',
    ];


     // Seller shop (receiver)
    public function sellerShop()
    {
        return $this->belongsTo(Shop::class, 'seller_id');
    }

    // Buyer user (sender)
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
