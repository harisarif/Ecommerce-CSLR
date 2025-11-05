<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Cart extends Model
{
    protected $table = 'cart_products';

    protected $fillable = [
        'cart_id',
        'customer_id',
        'products_data',
        'instance',
    ];

    protected $casts = [
        'products_data' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($cart) {
            if (empty($cart->cart_id)) {
                $cart->cart_id = (string) Str::uuid();
            }

            if (Auth::check() && empty($cart->customer_id)) {
                $cart->customer_id = Auth::id();
            }
        });
    }

    public static function findOrCreateCart($cartId = null): self
    {
        if ($cartId) {
            return self::firstOrCreate(['cart_id' => $cartId], [
                'products_data' => [],
                'customer_id' => Auth::id(),
            ]);
        }

        return self::create([
            'cart_id' => (string) Str::uuid(),
            'customer_id' => Auth::id(),
            'products_data' => [],
        ]);
    }

    public function addItem(array $item): void
    {
        $products = collect($this->products_data ?? []);
        $existingKey = $products->search(function ($p) use ($item) {
            return (int)$p['product_id'] === (int)$item['product_id'] &&
                (int)($p['variation_option_id'] ?? 0) === (int)($item['variation_option_id'] ?? 0);
        });

        if ($existingKey !== false) {
            $existingItem = $products->get($existingKey);
            $existingItem['quantity'] += $item['quantity'] ?? 1;
            $existingItem['total_price'] = $existingItem['quantity'] * $existingItem['product_price'];
            $products->put($existingKey, $existingItem);
        } else {
            $products->push([
                'product_id' => $item['product_id'],
                'variation_option_id' => $item['variation_option_id'] ?? null,
                'product_name' => $item['product_name'] ?? null,
                'product_price' => $item['product_price'] ?? 0,
                'product_image' => $item['product_image'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'total_price' => ($item['product_price'] ?? 0) * ($item['quantity'] ?? 1),
                'seller_id' => $item['seller_id'] ?? null,
                'shop_id' => $item['shop_id'] ?? null,
                'shop_name' => $item['shop_name'] ?? '',
                'product_type' => $item['product_type'] ?? 'physical',
            ]);
        }


        $this->products_data = $products->values();
        $this->save();
    }


    public function removeItem($productId, $variationId = null): void
    {
        $this->products_data = collect($this->products_data)
            ->reject(function ($item) use ($productId, $variationId) {
                return $item['product_id'] == $productId &&
                       ($item['variation_option_id'] ?? null) == $variationId;
            })
            ->values()
            ->toArray();

        $this->save();
    }

    public function clearItems(): void
    {
        $this->products_data = [];
        $this->save();
    }
}
