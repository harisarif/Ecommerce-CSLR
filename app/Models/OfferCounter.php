<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfferCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'sender_id',
        'recipient_id',
        'price',
        'type',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // 🔗 Relationships
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
