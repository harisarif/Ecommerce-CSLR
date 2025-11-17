<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Style extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // If a product can have many styles (recommended)
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_style');
    }
}
