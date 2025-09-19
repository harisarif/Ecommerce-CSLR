<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;

    // Table name (optional, Laravel will guess from class name)
    protected $table = 'brands';

    // Primary key
    protected $primaryKey = 'id';

    // Auto-incrementing
    public $incrementing = true;

    // Key type
    protected $keyType = 'int';

    // Timestamps (since you have created_at but no updated_at)
    public $timestamps = false;

    // Fillable fields (mass assignment)
    protected $fillable = [
        'name',
        'name_data',
        'category_data',
        'image_path',
        'show_on_slider',
        'created_at',
    ];


    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
