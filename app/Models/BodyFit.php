<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodyFit extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // If a product has one body fit
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
