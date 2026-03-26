<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TrustapService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\{
    Cart, Product, Currency, Offer, OfferCounter,
    Order, OrderProduct, ShippingAddress, Shop, User
};

class CheckoutController extends Controller
{
    protected $trustap;

    public function __construct(TrustapService $trustap)
    {
        $this->trustap = $trustap;
    }

    public function createCheckout(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'cart_id' => 'nullable|string',
            'product_id' => 'nullable|integer|exists:products,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'offer_id' => 'nullable|integer|exists:offers,id',

            'address' => 'nullable|array',
            'address.first_name' => 'nullable|string',
            'address.last_name' => 'nullable|string',
            'address.email' => 'nullable|email',
            'address.phone_number' => 'nullable|string',
            'address.address' => 'nullable|string',
            'address.city' => 'nullable|string',
            'address.zip_code' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $products = collect();
            $currency = Currency::where('status', 1)->value('code') ?? 'AED';

            /*
            |--------------------------------------------------------------------------
            | OFFER CHECKOUT
            |--------------------------------------------------------------------------
            */

            if ($request->filled('offer_id')) {

                $offer = Offer::with('product')->findOrFail($request->offer_id);
                $product = $offer->product;

                $latestCounter = OfferCounter::where('offer_id', $offer->id)
                    ->latest()
                    ->first();

                $price = $latestCounter ? $latestCounter->price : $offer->price;

                $products->push([
                    'product_id' => $product->id,
                    'name' => $product->slug,
                    'amount' => $price,
                    'quantity' => 1,
                    'shop_id' => $product->shop_id,
                    'offer_id' => $offer->id,
                    'offer_counter_id' => $latestCounter?->id
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | DIRECT PRODUCT CHECKOUT
            |--------------------------------------------------------------------------
            */

            elseif ($request->filled('product_id') && !$request->filled('cart_id')) {

                $product = Product::with('shop')->findOrFail($request->product_id);

                $products->push([
                    'product_id' => $product->id,
                    'name' => $product->slug,
                    'amount' => $product->price,
                    'quantity' => 1,
                    'shop_id' => $product->shop_id
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | CART CHECKOUT
            |--------------------------------------------------------------------------
            */

            elseif ($request->filled('cart_id')) {

                $cart = Cart::where('cart_id', $request->cart_id)->firstOrFail();
                $cartProducts = collect($cart->products_data);

                if ($request->filled('shop_id')) {
                    $filtered = $cartProducts->where('shop_id', $request->shop_id);
                } elseif ($request->filled('product_id')) {
                    $filtered = $cartProducts->where('product_id', $request->product_id);
                } else {
                    return response()->json(['message' => 'Provide product_id or shop_id'], 422);
                }

                if ($filtered->isEmpty()) {
                    return response()->json(['message' => 'No products found'], 404);
                }

                $products = $filtered->map(fn($p) => [
                    'product_id' => $p['product_id'],
                    'name' => $p['product_name'],
                    'amount' => $p['product_price'],
                    'quantity' => $p['quantity'],
                    'shop_id' => $p['shop_id']
                ])->values();
            }

            else {
                return response()->json(['message' => 'Invalid checkout scenario'], 422);
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL CALCULATION
            |--------------------------------------------------------------------------
            */

            $total = $products->sum(fn($p) => $p['amount'] * $p['quantity']);

            \Log::info('Creating Trustap Transactionsssssssssssssssssssss', [
                'buyer_id' => $user->trustap_guest_user_id,
                'products' => $products,
                'total' => $total
            ]);

            $shop = Shop::with('user')->findOrFail($products->first()['shop_id']);
            $seller = $shop->user;

            if (!$user->trustap_guest_user_id) {
                throw new \Exception('Buyer Trustap ID missing');
            }

            if (!$seller->trustap_guest_user_id) {
                throw new \Exception('Seller Trustap ID missing');
            }

            \Log::info('Creating Trustap Transaction', [
                'buyer_id' => $user->trustap_guest_user_id,
                'seller_id' => $seller->trustap_guest_user_id,
                'total' => $total
            ]);

            $transaction = $this->trustap->createTransaction(
                $user->trustap_guest_user_id,
                $seller->trustap_guest_user_id,
                intval($total * 100),
                "Marketplace order"
            );
           // ensure valid access token
            // if (!$user->trustap_access_token || now()->greaterThan($user->trustap_token_expires_at)) {
            //     $user->trustap_access_token = $this->trustap->refreshAccessToken($user);
            // }

            // // create transaction
            // $transaction = $this->trustap->createTransactionRegistered($user, $seller, intval($total*100), "Marketplace order");
            // \Log::info('Trustap createTransaction response', $transaction);

            $transactionId = $transaction['id'] ?? null;

            if (!$transactionId) {
                throw new \Exception('Trustap transaction creation failed');
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE ORDER
            |--------------------------------------------------------------------------
            */

            $order = Order::create([
                'buyer_id' => $user->id,
                'price_total' => $total,
                'price_currency' => strtolower($currency),
                'payment_method' => 'trustap',
                'payment_status' => 'pending',
                'trustap_transaction_id' => $transactionId,
                'trustap_status' => 'created'
            ]);

            /*
            |--------------------------------------------------------------------------
            | ORDER PRODUCTS
            |--------------------------------------------------------------------------
            */

            foreach ($products as $p) {

                OrderProduct::create([
                    'order_id' => $order->id,
                    'seller_id' => $shop->user_id,
                    'buyer_id' => $user->id,
                    'product_id' => $p['product_id'],
                    'product_title' => $p['name'],
                    'product_unit_price' => $p['amount'],
                    'product_quantity' => $p['quantity'],
                    'product_total_price' => $p['amount'] * $p['quantity'],
                    'offer_id' => $p['offer_id'] ?? null,
                    'offer_counter_id' => $p['offer_counter_id'] ?? null
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | REMOVE CART ITEMS AFTER ORDER CREATION
            |--------------------------------------------------------------------------
            */

            if ($request->filled('cart_id')) {

                $cart = Cart::where('cart_id', $request->cart_id)->first();

                if ($cart && !empty($cart->products_data)) {

                    $remainingProducts = collect($cart->products_data)
                        ->reject(fn($item) =>
                            $products->pluck('product_id')->contains($item['product_id'])
                        )
                        ->values()
                        ->toArray();

                    $cart->products_data = $remainingProducts;
                    $cart->save();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SHIPPING ADDRESS
            |--------------------------------------------------------------------------
            */

            if ($request->address) {

                ShippingAddress::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'first_name' => $request->address['first_name'] ?? '',
                    'last_name' => $request->address['last_name'] ?? '',
                    'email' => $request->address['email'] ?? '',
                    'phone_number' => $request->address['phone_number'] ?? '',
                    'address' => $request->address['address'] ?? '',
                    'city' => $request->address['city'] ?? '',
                    'zip_code' => $request->address['zip_code'] ?? ''
                ]);
            }

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | TRUSTAP CHECKOUT URL
            |--------------------------------------------------------------------------
            */

            $redirectUrl = config('app.frontend_url') . '/order-success';

            $checkoutUrl = config('services.trustap.actions_url')
                . "/online/transactions/{$transactionId}/guest_pay"
                . "?redirect_uri=" . urlencode($redirectUrl);

            return response()->json([
                'checkout_url' => $checkoutUrl,
                'transaction_id' => $transactionId,
                'order_id' => $order->id,
                'currency' => $currency
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error('Trustap Checkout Failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Checkout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    
}
