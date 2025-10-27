<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'app_category_id',
        'size_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function appCategory()
    {
        return $this->belongsTo(AppCategory::class, 'app_category_id');
    }

        /**
     * 🧩 Relation to Size
     */
    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }
}
