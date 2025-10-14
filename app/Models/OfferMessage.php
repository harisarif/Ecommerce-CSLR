<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferMessage extends Model
{
    protected $fillable = ['offer_id','sender_id','recipient_id','body','meta','is_read'];

    protected $casts = [
        'meta' => 'array',
        'is_read' => 'boolean',
    ];

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
