<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Image extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'image_default',
        'image_big',
        'image_small',
        'is_main',
        'storage'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_main' => 'boolean',
    ];

    /**
     * Get the product that owns the image.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope a query to only include main images.
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Get the full URL for the default image.
     */
    // public function getDefaultImageUrl()
    // {
    //     if ($this->storage === 'local') {
    //         return asset('storage/' . $this->image_default);
    //     }
    //     return $this->image_default;
    // }

    // /**
    //  * Get the full URL for the big image.
    //  */
    // public function getBigImageUrl()
    // {
    //     if ($this->storage === 'local') {
    //         return asset('storage/' . $this->image_big);
    //     }
    //     return $this->image_big;
    // }

    // /**
    //  * Get the full URL for the small image.
    //  */
    // public function getSmallImageUrl()
    // {
    //     if ($this->storage === 'local') {
    //         return asset('storage/' . $this->image_small);
    //     }
    //     return $this->image_small;
    // }


        public function getImageDefaultAttribute($value)
    {
        return $value ? 'uploads/' . $value : null;
    }

    /**
     * Get the big image path with uploads/ prefix.
     */
    public function getImageBigAttribute($value)
    {
        return $value ? 'uploads/' . $value : null;
    }

    /**
     * Get the small image path with uploads/ prefix.
     */
    public function getImageSmallAttribute($value)
    {
        return $value ? 'uploads/' . $value : null;
    }
}
