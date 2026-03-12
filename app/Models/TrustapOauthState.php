<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrustapOauthState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'state',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
