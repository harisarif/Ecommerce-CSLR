<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'image_paths', 'count'];

    protected $casts = [
        'image_paths' => 'array',
    ];

    protected $hidden = ['created_at', 'updated_at','product_id','id'];
    

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * ✅ Automatically transform all image paths to full URLs.
     * This replaces each entry inside image_paths[] with its absolute URL.
     */
    public function getImagePathsAttribute($value)
    {
        $paths = json_decode($value, true) ?? [];

        return collect($paths)->map(function ($path) {
            return url('uploads/' . ltrim($path, '/'));
        })->values()->toArray();
    }
}
