<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'phone',
        'address',
        'settings',
        'image',
        'stripe_account_id',
        'platform_commission_percent',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    // owner
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // products in this shop
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // boot to auto-generate slug if not provided
    protected static function booted()
    {
        static::creating(function ($shop) {
            if (empty($shop->slug) && !empty($shop->name)) {
                $base = Str::slug($shop->name);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $shop->slug = $slug;
            }
        });
    }


    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url($this->image); // generates full URL
        }
        return null;
    }

    // reviews
    public function reviews()
    {
        return $this->hasMany(ShopReview::class);
    }

    // followers
    public function followers()
    {
        return $this->belongsToMany(User::class, 'shop_followers');
    }


    public function isStripeReady(): bool
    {
        if (!$this->stripe_account_id) {
            return false;
        }

        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            $account = \Stripe\Account::retrieve($this->stripe_account_id);

            return $account->charges_enabled === true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
