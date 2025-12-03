<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductInterested extends Model
{
    protected $table = 'product_interested';

    protected $fillable = [
        'product_id',
        'product_owner_id',
        'viewer_id',
        'viewer_shop_id'
    ];
}
