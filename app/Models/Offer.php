<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'buyer_id',
        'seller_id',
        'price',
        'message',
        'status',
        'responded_at',
        'buyer_read',
        'seller_read',
        'expires_at',
        'is_paid',
        'is_owner_offer'
    ];

    protected $casts = [
        'buyer_read' => 'boolean',
        'seller_read' => 'boolean',
        'responded_at' => 'datetime',
        'expires_at' => 'datetime', 
        'is_paid' => 'boolean',
        'is_owner_offer' => 'boolean',
    ];

    protected $appends = ['is_expired'];

    // relations
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    // convenience scope
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('buyer_id', $userId)->orWhere('seller_id', $userId);
        });
    }

    public function getIsExpiredAttribute()
    {
        return $this->expires_at && now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function counters()
    {
        return $this->hasMany(OfferCounter::class);
    }

    public function shop()
    {
        return $this->product()->with('shop');
    }

}
