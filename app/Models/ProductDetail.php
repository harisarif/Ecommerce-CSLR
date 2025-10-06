<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetail extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    public $timestamps = false; // 👈 add this line
    protected $table = 'product_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'lang_id',
        'title',
        'description',
        'short_description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'product_id' => 'integer',
        'lang_id' => 'integer',
    ];

    /**
     * Get the product that owns the details.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the language of the details.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang_id');
    }

    /**
     * Scope a query to only include details for a specific language.
     */
    public function scopeForLanguage($query, $languageId)
    {
        return $query->where('lang_id', $languageId);
    }

    /**
     * Scope a query to only include details for a specific product.
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Get a localized version of the product details.
     * 
     * @param int $languageId
     * @param int $fallbackLanguageId
     * @return ProductDetail|null
     */
    public static function getLocalized($productId, $languageId, $fallbackLanguageId = 1)
    {
        // First try to get the details in the requested language
        $details = self::where('product_id', $productId)
                      ->where('lang_id', $languageId)
                      ->first();
        
        // If not found and a fallback is specified, try the fallback language
        if (!$details && $fallbackLanguageId) {
            $details = self::where('product_id', $productId)
                          ->where('lang_id', $fallbackLanguageId)
                          ->first();
        }
        
        // If still not found, get any available translation
        if (!$details) {
            $details = self::where('product_id', $productId)->first();
        }
        
        return $details;
    }

    /**
     * Get the first line or a truncated version of the description.
     * 
     * @param int $length
     * @return string
     */
    public function getFirstLine($length = 200)
    {
        if (empty($this->description)) {
            return '';
        }
        
        // Remove HTML tags and get the first line
        $text = strip_tags($this->description);
        $lines = explode("\n", $text);
        $firstLine = trim($lines[0]);
        
        // Truncate if needed
        if (mb_strlen($firstLine) > $length) {
            return mb_substr($firstLine, 0, $length) . '...';
        }
        
        return $firstLine;
    }

    /**
     * Check if the details are in the specified language.
     */
    public function isLanguage($languageId): bool
    {
        return $this->lang_id == $languageId;
    }

    /**
     * Get the available languages for a product.
     */
    public static function getAvailableLanguages($productId)
    {
        return self::where('product_id', $productId)
                  ->with('language')
                  ->get()
                  ->pluck('language')
                  ->filter();
    }
}
