<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visibility' => 'boolean',
        'is_featured' => 'boolean',
        'show_on_main_menu' => 'boolean',
        'show_image_on_main_menu' => 'boolean',
        'show_products_on_index' => 'boolean',
        'show_subcategory_products' => 'boolean',
        'show_description' => 'boolean',
        'parent_id' => 'integer',
        'tree_id' => 'integer',
        'level' => 'integer',
        'category_order' => 'integer',
        'featured_order' => 'integer',
        'homepage_order' => 'integer',
    ];

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('category_order');
    }

    /**
     * Get all descendants of the category.
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all products in this category.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get the tree this category belongs to.
     */
    public function tree()
    {
        return $this->belongsTo(Category::class, 'tree_id');
    }

    /**
     * Scope a query to only include root categories (no parent).
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id')->orWhere('parent_id', 0);
    }

    /**
     * Scope a query to only include visible categories.
     */
    public function scopeVisible($query)
    {
        return $query->where('visibility', true);
    }

    /**
     * Scope a query to only include featured categories.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
            ->orderBy('featured_order')
            ->orderBy('name');
    }

    /**
     * Scope a query to only include categories for main menu.
     */
    public function scopeForMainMenu($query)
    {
        return $query->where('show_on_main_menu', true)
            ->orderBy('category_order')
            ->orderBy('name');
    }

    /**
     * Get the full path of the category.
     */
    public function getFullPathAttribute()
    {
        $path = [];
        $category = $this;

        while ($category) {
            array_unshift($path, $category->name);
            $category = $category->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get the URL for the category.
     */
    public function getUrlAttribute()
    {
        return route('category.show', $this->slug);
    }

    /**
     * Get the image URL.
     */
    public function getImageUrlAttribute()
    {
        if (empty($this->image)) {
            return asset('assets/img/no-image.jpg');
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    /**
     * Get all products including subcategories.
     */
    public function getAllProducts()
    {
        $categoryIds = $this->getAllDescendantIds();
        $categoryIds[] = $this->id;

        return Product::whereIn('category_id', $categoryIds)
            ->where('status', true)
            ->where('visibility', true)
            ->get();
    }

    /**
     * Get all descendant category IDs.
     */
    protected function getAllDescendantIds()
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    /**
     * Check if the category is a child of the given category.
     */
    public function isChildOf(Category $category)
    {
        return $this->parent_id === $category->id;
    }

    /**
     * Check if the category is a descendant of the given category.
     */
    public function isDescendantOf(Category $category)
    {
        if ($this->parent_id === $category->id) {
            return true;
        }

        if ($this->parent) {
            return $this->parent->isDescendantOf($category);
        }

        return false;
    }

    public function sizes()
    {
        return $this->hasManyThrough(
            Size::class,
            ProductSize::class,
            'product_id',   // Foreign key on product_sizes
            'id',           // Local key on sizes
            'id',           // Local key on categories
            'size_id'       // Foreign key on product_sizes
        );
    }

    // Or if you want sizes via products directly:
    public function productSizes()
    {
        return $this->hasManyThrough(
            ProductSize::class,
            Product::class,
            'category_id',  // Foreign key on products table
            'product_id',   // Foreign key on product_sizes table
            'id',           // Local key on categories table
            'id'            // Local key on products table
        );
    }
}
