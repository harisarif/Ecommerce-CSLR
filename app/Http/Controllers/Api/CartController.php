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

        return response()->json([
            'cart_id' => $cart->cart_id,
            'products' => $cart->products_data ?? [],
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

        // get product info from DB
        $product = \App\Models\Product::where('id', $request->product_id)
                    ->active()
                    ->firstOrFail();

        $cart = Cart::findOrCreateCart($request->input('cart_id'));

        $cart->addItem([
            'product_id' => $product->id,
            'variation_option_id' => $request->variation_option_id,
            'product_name' => $product->slug, // or $product->details->title if you prefer
            'product_price' => $product->price_discounted > 0 ? $product->price_discounted : $product->price,
            'product_image' => $product->main_image?->url ?? null,
            'quantity' => $request->quantity,
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

    public function checkout(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
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

        $products = $cart->products_data;
        $grandTotal = 0;

        $formattedProducts = collect($products)->map(function ($product) use (&$grandTotal) {
            $total = isset($product['total_price']) ? $product['total_price'] : 0;
            $grandTotal += $total;

            return [
                'product_id'   => $product['product_id'] ?? null,
                'name'         => $product['product_name'] ?? '',
                'image'        => $product['product_image '] ?? '',
                'quantity'     => $product['quantity'] ?? 1,
                'unit_price'   => $product['unit_price'] ?? 0,
                'total_price'  => $total,
            ];
        });

        return response()->json([
            'cart_id'      => $cart->cart_id,
            'products'     => $formattedProducts,
            'total_price'  => $grandTotal,
        ]);
    }


    public function updateQuantity(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|string',
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'variation_option_id' => 'nullable|integer',
        ]);

        $cart = \App\Models\Cart::where('cart_id', $request->cart_id)->first();

        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $products = collect($cart->products_data);

        $productFound = false;

        $products = $products->map(function ($item) use ($request, &$productFound) {
            if (
                $item['product_id'] == $request->product_id &&
                ($item['variation_option_id'] ?? null) == ($request->variation_option_id ?? null)
            ) {
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
