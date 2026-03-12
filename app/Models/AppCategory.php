<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppCategory extends Model
{
    protected $table = 'app_categories';

    protected $fillable = [
        'slug',
        'parent_id',
        'tree_id',
        'level',
        'parent_tree',
        'title_meta_tag',
        'description',
        'keywords',
        'category_order',
        'featured_order',
        'homepage_order',
        'visibility',
        'is_featured',
        'show_on_main_menu',
        'show_image_on_main_menu',
        'show_products_on_index',
        'show_subcategory_products',
        'storage',
        'image',
        'show_description',
    ];


    protected $hidden = [
        'category_order',
        'featured_order',
        'homepage_order',
        'visibility',
        'is_featured',
        'show_on_main_menu',
        'show_image_on_main_menu',
        'show_products_on_index',
        'show_subcategory_products',
        'storage',
        'image',
        'show_description',
    ];

    // Parent
    public function parent()
    {
        return $this->belongsTo(AppCategory::class, 'parent_id');
    }

    // Children
    public function children()
    {
        return $this->hasMany(AppCategory::class, 'parent_id');
    }

    // Recursive descendants
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }


    public function products()
    {
        return $this->hasMany(Product::class, 'app_category_id');
    }

    public function getTotalProductsAttribute()
    {
        // Count products of this category (NON-vacation shops only)
        $count = $this->products()
            ->where('status', 1)
            ->whereHas('shop', function ($q) {
                $q->where('vacation_mode', false);
            })
            ->count();

        // Recursively count children products
        foreach ($this->children as $child) {
            $count += $child->total_products;
        }

        return $count;
    }

    
}
