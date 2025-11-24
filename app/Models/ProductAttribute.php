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

    protected $appends = ['type_label'];


    public function product()
    {
        return $this->belongsTo(Product::class);
    }


    public function getTypeLabelAttribute()
    {
        $type = $this->type;

        // Handle parcel sizes
        if (str_starts_with($type, 'parcel_size_')) {
            return 'Parcel Size';
        }

        // fallback readable conversion
        return ucwords(str_replace('_', ' ', $type));
    }

}
