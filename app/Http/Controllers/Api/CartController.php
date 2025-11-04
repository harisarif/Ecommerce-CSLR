<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Get cart for the authenticated user or by cart_id (guest)
     */
    public function index(Request $request)
    {
        $cartId = $request->input('cart_id');

        $cart = $cartId
            ? Cart::where('cart_id', $cartId)->first()
            : Cart::where('customer_id', Auth::id())->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart not found',
                'cart_id' => null,
                'products' => [],
            ]);
        }

        // Optionally group by shop for frontend
        $grouped = collect($cart->products_data ?? [])->groupBy('shop_id')->map(function ($items) {
            return [
                'shop_id' => $items->first()['shop_id'],
                'shop_name' => $items->first()['shop_name'],
                'products' => $items->values(),
                'subtotal' => $items->sum('total_price'),
            ];
        })->values();

        return response()->json([
            'cart_id' => $cart->cart_id,
            'cart' => $grouped,
        ]);
    }

    /**
     * Add item to new or existing cart
     */
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variation_option_id' => 'nullable|integer',
            'cart_id' => 'nullable|string',
        ]);

        $product = \App\Models\Product::with('shop')->active()->findOrFail($request->product_id);

        $cart = Cart::findOrCreateCart($request->input('cart_id'));

        $cart->addItem([
            'product_id' => $product->id,
            // 'variation_option_id' => $request->variation_option_id,
            'product_name' => $product->details()->first()->title ?? $product->slug,
            'product_price' => $product->price_discounted > 0 ? $product->price_discounted : $product->price,
            'product_image' => $product->main_image ?? null,
            'quantity' => $request->quantity,
            'shop_id' => $product->shop_id,
            'shop_name' => $product->shop?->name,
            'seller_id' => $product->shop?->user_id,
        ]);

        return response()->json([
            'message' => 'Product added to cart',
            'cart_id' => $cart->cart_id,
            'products' => $cart->products_data,
        ]);
    }

    /**
     * Remove an item from cart
     */
    public function remove(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
            'product_id' => 'required|integer',
            'variation_option_id' => 'nullable|integer',
        ]);

        $cart = Cart::where('cart_id', $request->input('cart_id'))->firstOrFail();
        $cart->removeItem($request->product_id, $request->variation_option_id);

        return response()->json([
            'message' => 'Product removed from cart',
            'products' => $cart->products_data,
        ]);
    }


    /**
     * Clear all items from the cart
     */
    public function clear(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
        ]);

        $cart = Cart::where('cart_id', $request->input('cart_id'))->firstOrFail();
        $cart->clearItems();

        return response()->json([
            'message' => 'Cart cleared',
            'products' => [],
        ]);
    }


    //this funciton is used for checkout to remove product from cart 
    // public function checkout(Request $request)
    // {
    //     $request->validate([
    //         'cart_id' => 'required|string',
    //         'shop_id' => 'nullable|integer',  // if user clicks "checkout all from shop"
    //         'product_id' => 'nullable|integer', // if user clicks "checkout single product"
    //     ]);

    //     $cart = Cart::where('cart_id', $request->input('cart_id'))->first();

    //     if (!$cart || empty($cart->products_data)) {
    //         return response()->json([
    //             'message' => 'Cart not found or empty',
    //             'cart_id' => $request->input('cart_id'),
    //             'products' => [],
    //             'total_price' => 0,
    //         ]);
    //     }

    //     $products = collect($cart->products_data);

    //     // 🚫 Restrict checkout of full cart if multiple shops exist
    //     $uniqueShops = $products->pluck('shop_id')->unique();

    //     if (!$request->filled('shop_id') && !$request->filled('product_id') && $uniqueShops->count() > 1) {
    //         return response()->json([
    //             'message' => 'Checkout blocked — your cart contains products from multiple shops. Please checkout per shop.',
    //             'shops' => $uniqueShops->values(),
    //         ], 422);
    //     }

    //     // Filter items being checked out
    //     if ($request->filled('product_id')) {
    //         $filtered = $products->where('product_id', $request->product_id);
    //     } elseif ($request->filled('shop_id')) {
    //         $filtered = $products->where('shop_id', $request->shop_id);
    //     } else {
    //         // If no filter provided but only one shop exists, checkout all items
    //         $filtered = $products;
    //     }

    //     if ($filtered->isEmpty()) {
    //         return response()->json(['message' => 'No matching products found for checkout.'], 404);
    //     }

    //     $grandTotal = $filtered->sum('total_price');

    //     // 🧩 Remove checked-out items from cart
    //     $remaining = $products->reject(function ($item) use ($request, $uniqueShops) {
    //         if ($request->filled('product_id')) {
    //             return $item['product_id'] == $request->product_id;
    //         }
    //         if ($request->filled('shop_id')) {
    //             return $item['shop_id'] == $request->shop_id;
    //         }
    //         // If single-shop cart, allow full cart checkout
    //         if ($uniqueShops->count() === 1) {
    //             return true;
    //         }
    //         return false;
    //     })->values();

    //     $cart->products_data = $remaining;
    //     $cart->save();

    //     return response()->json([
    //         'message' => 'Checkout successful, items removed from cart',
    //         'cart_id' => $cart->cart_id,
    //         'checked_out_products' => $filtered->values(),
    //         'total_price' => $grandTotal,
    //         'shop_id' => $filtered->first()['shop_id'] ?? null,
    //         'shop_name' => $filtered->first()['shop_name'] ?? null,
    //         'remaining_cart' => $remaining->values(),
    //     ]);
    // }


        public function checkout(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
            'shop_id' => 'nullable|integer',  // if user clicks "checkout all from shop"
            'product_id' => 'nullable|integer', // if user clicks "checkout single product"
        ]);

        $cart = Cart::where('cart_id', $request->input('cart_id'))->first();

        if (!$cart || empty($cart->products_data)) {
            return response()->json([
                'message' => 'Cart not found or empty',
                'cart_id' => $request->input('cart_id'),
                'products' => [],
                'total_price' => 0,
            ]);
        }

        $products = collect($cart->products_data);

        // Filter by shop or by product (depending on what user clicked)
        if ($request->filled('product_id')) {
            $filtered = $products->where('product_id', $request->product_id);
        } elseif ($request->filled('shop_id')) {
            $filtered = $products->where('shop_id', $request->shop_id);
        } else {
            return response()->json(['message' => 'Please provide product_id or shop_id for checkout.'], 422);
        }

        if ($filtered->isEmpty()) {
            return response()->json(['message' => 'No matching products found for checkout.'], 404);
        }

        $grandTotal = $filtered->sum('total_price');

        return response()->json([
            'message' => 'Checkout data ready',
            'cart_id' => $cart->cart_id,
            'products' => $filtered->values(),
            'total_price' => $grandTotal,
            'shop_id' => $filtered->first()['shop_id'] ?? null,
            'shop_name' => $filtered->first()['shop_name'] ?? null,
        ]);
    }


    public function updateQuantity(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('cart_id', $request->cart_id)->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $products = collect($cart->products_data);
        $productFound = false;

        $products = $products->map(function ($item) use ($request, &$productFound) {
            if ($item['product_id'] == $request->product_id) {
                $item['quantity'] = $request->quantity;
                $item['total_price'] = $item['product_price'] * $request->quantity;
                $productFound = true;
            }
            return $item;
        });

        if (!$productFound) {
            return response()->json(['message' => 'Product not found in cart'], 404);
        }

        $cart->products_data = $products->values()->toArray();
        $cart->save();

        return response()->json([
            'message' => 'Product quantity updated',
            'cart_id' => $cart->cart_id,
            'products' => $cart->products_data,
        ]);
    }
}
