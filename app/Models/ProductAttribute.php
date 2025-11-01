<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    protected $fillable = ['product_id', 'type', 'value'];

          protected $hidden = [
            'created_at',
            'updated_at'
      ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
