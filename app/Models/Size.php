<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $fillable = [
        'name',
        'type',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_sizes')
            ->withTimestamps();
    }

    public function productSizes()
    {
        return $this->hasMany(ProductSize::class);
    }
}
