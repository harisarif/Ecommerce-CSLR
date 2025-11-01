<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wishlist extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wishlist';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'user_id'
    ];

    /**
     * Get the user that owns the wishlist item.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that is in the wishlist.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope a query to only include wishlist items for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if a product is in user's wishlist.
     */
    public static function isInWishlist($userId, $productId)
    {
        return static::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->exists();
    }

    /**
     * Add a product to user's wishlist.
     */
    public static function addToWishlist($userId, $productId)
    {
        if (!static::isInWishlist($userId, $productId)) {
            return static::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
        }
        return null;
    }

    /**
     * Remove a product from user's wishlist.
     */
    public static function removeFromWishlist($userId, $productId)
    {
        return static::where('user_id', $userId)
                    ->where('product_id', $productId)
                    ->delete();
    }

    /**
     * Get user's wishlist with product details.
     */
    public static function getUserWishlist($userId)
    {
        return static::with(['product' => function($query) {
            $query->with(['details']);
        }])
        ->where('user_id', $userId)
        ->get()
        ->pluck('product');
    }
}
