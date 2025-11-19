<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'product_type',
        'listing_type',
        'sku',
        'category_id',
        'app_category_id',
        'price',
        'price_discounted',
        'currency',
        'discount_rate',
        'vat_rate',
        'user_id',
        'status',
        'is_promoted',
        'promote_start_date',
        'promote_end_date',
        'promote_plan',
        'promote_day',
        'is_special_offer',
        'special_offer_date',
        'visibility',
        'rating',
        'pageviews',
        'demo_url',
        'external_link',
        'files_included',
        'stock',
        'shipping_class_id',
        'shipping_delivery_time_id',
        'multiple_sale',
        'digital_file_download_link',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'zip_code',
        'brand_id',
        'is_sold',
        'is_deleted',
        'is_draft',
        'is_edited',
        'is_active',
        'is_free_product',
        'is_rejected',
        'reject_reason',
        'is_affiliate',
        'shop_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'integer',
        'price_discounted' => 'integer',
        'discount_rate' => 'integer',
        'vat_rate' => 'double',
        'status' => 'boolean',
        'is_promoted' => 'boolean',
        'is_special_offer' => 'boolean',
        'visibility' => 'boolean',
        'stock' => 'integer',
        'multiple_sale' => 'boolean',
        'is_sold' => 'boolean',
        'is_deleted' => 'boolean',
        'is_draft' => 'boolean',
        'is_edited' => 'boolean',
        'is_active' => 'boolean',
        'is_free_product' => 'boolean',
        'is_rejected' => 'boolean',
        'is_affiliate' => 'boolean',
        'promote_start_date' => 'datetime',
        'promote_end_date' => 'datetime',
        'special_offer_date' => 'datetime',
    ];

    protected $hidden = [
        'product_type',
        'listing_type',
        'vat_rate',
        'is_sold',
        'is_deleted',
        'is_draft',
        'is_edited',
        'is_active',
        'is_free_product',
        'is_rejected',
        'reject_reason',
        'is_affiliate',
        'shipping_class_id',
        'shipping_delivery_time_id',
        'multiple_sale',
        'digital_file_download_link',
        'pageviews',
        'demo_url',
        'external_link',
        'files_included',
        'is_promoted',
        'promote_start_date',
        'promote_end_date',
        'promote_plan',
        'promote_day',
        'is_special_offer',
        'special_offer_date',
        'visibility',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'zip_code',
        'mainImageRelation'
    ];

    protected $appends = ['is_favorite', 'main_image', 'product_images' , 'isOfferSent'];

    public function getIsOfferSentAttribute()
    {
        $user = Auth::user();

        if (!$user) {
            return false; // Not logged in, cannot have sent an offer
        }

        // Check if the authenticated user (buyer) already has a pending, non-expired offer
        return Offer::where('product_id', $this->id)
            ->where('buyer_id', $user->id)   // Only check as buyer
            ->where('status', 'pending')    // Only pending offers
            ->where('expires_at', '>', now()) // Not expired
            ->exists();
    }

    public function getIsFavoriteAttribute()
    {
        $user = Auth::user();

        if (!$user) {
            return false; // not logged in, so not favorite
        }

        return \App\Models\Wishlist::where('user_id', $user->id)
            ->where('product_id', $this->id)
            ->exists();
    }


    public function productImagesRelation()
    {
        return $this->hasOne(ProductImage::class, 'product_id');
    }

    // Accessor to return all image paths (including main_image)
    public function getProductImagesAttribute()
    {
        $paths = [];

        // 1️⃣ Add main_image (from images table) if exists
        $mainImage = $this->main_image;
        if ($mainImage) {
            $paths[] = $mainImage;
        }

        // 2️⃣ Add gallery images (from product_images table)
        $gallery = $this->relationLoaded('productImagesRelation')
            ? $this->productImagesRelation
            : $this->productImagesRelation()->first();

        if ($gallery && !empty($gallery->image_paths)) {
            foreach ($gallery->image_paths as $path) {
                if (!in_array($path, $paths)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }
    /**
     * Get the user that owns the product.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category that owns the product.
     */
    /**
     * Get the product details (multilingual).
     */
    public function details()
    {
        return $this->hasMany(ProductDetail::class);
    }

    /**
     * Get the license keys for the product.
     */
    public function licenseKeys()
    {
        return $this->hasMany(ProductLicenseKey::class);
    }

    /**
     * Get the search indexes for the product.
     */
    public function searchIndexes()
    {
        return $this->hasMany(ProductSearchIndex::class);
    }

    /**
     * Get the user that owns the product.
     */
    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('is_deleted', false)
            ->where('status', true);
    }

    /**
     * Scope a query to only include promoted products.
     */
    public function scopePromoted($query)
    {
        $now = now();
        return $query->where('is_promoted', true)
            ->where('promote_start_date', '<=', $now)
            ->where('promote_end_date', '>=', $now);
    }

    /**
     * Scope a query to only include special offer products.
     */
    public function scopeSpecialOffers($query)
    {
        return $query->where('is_special_offer', true)
            ->orderBy('special_offer_date', 'DESC');
    }

    /**
     * Get the product's price in a formatted way.
     */
    public function getFormattedPriceAttribute()
    {
        $currency = $this->currency ?: 'USD';
        return number_format($this->price / 100, 2) . ' ' . $currency;
    }

    /**
     * Get the product's discounted price in a formatted way.
     */
    public function getFormattedDiscountedPriceAttribute()
    {
        $price = $this->price_discounted ?: $this->price;
        $currency = $this->currency ?: 'USD';
        return number_format($price / 100, 2) . ' ' . $currency;
    }

    /**
     * Check if the product is on sale.
     */
    public function getIsOnSaleAttribute()
    {
        return $this->price_discounted > 0 && $this->price_discounted < $this->price;
    }

    /**
     * Get the discount percentage.
     */
    /**
     * Get the variations for the product.
     */
    public function variations()
    {
        return $this->hasMany(Variation::class)->where('parent_id', 0);
    }

    /**
     * Get all variations including child variations.
     */
    public function allVariations()
    {
        return $this->hasMany(Variation::class);
    }

    /**
     * Get all images for the product.
     */
    public function images()
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the main image for the product.
     */
    public function mainImageRelation()
    {
        return $this->hasOne(Image::class)->where('is_main', true);
    }

    public function getMainImageAttribute()
    {
        // If the relation is already loaded (via with()), use it directly
        $mainImage = $this->relationLoaded('mainImageRelation')
            ? $this->mainImageRelation
            : $this->mainImageRelation()->first();

        return $mainImage ? $mainImage->image_default : url('uploads/default.png');
    }
    /**
     * Get the default variation options for the product.
     */
    public function defaultVariationOptions()
    {
        return $this->hasManyThrough(
            VariationOption::class,
            Variation::class,
            'product_id',
            'variation_id'
        )->where('is_default', true);
    }

    /**
     * Get the wishlist items for this product.
     */
    public function wishlistItems()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the users who have this product in their wishlist.
     */
    public function wishlistedBy()
    {
        return $this->belongsToMany(User::class, 'wishlist', 'product_id', 'user_id')
            ->withTimestamps()
            ->withPivot('id');
    }

    /**
     * Check if the product is in a specific user's wishlist.
     */
    public function isInUserWishlist($userId)
    {
        return $this->wishlistItems()->where('user_id', $userId)->exists();
    }

    /**
     * Get the count of users who have this product in their wishlist.
     */
    public function wishlistCount()
    {
        return $this->wishlistItems()->count();
    }

    public function getDiscountPercentageAttribute()
    {
        if ($this->price_discounted > 0 && $this->price > 0) {
            return round((($this->price - $this->price_discounted) / $this->price) * 100);
        }
        return 0;
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function sizes()
    {
        return $this->belongsToMany(Size::class, 'product_sizes')
            ->withTimestamps();
    }
    public function productSizes()
    {
        return $this->hasMany(ProductSize::class);
    }
    public function appCategory()
    {
        return $this->belongsTo(AppCategory::class, 'app_category_id');
    }
    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function owner()
    {
        return $this->shop ? $this->shop->user() : $this->user();
    }
}
