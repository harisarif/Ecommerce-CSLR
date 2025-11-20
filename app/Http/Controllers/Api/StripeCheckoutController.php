<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\{Cart, Product, Currency, Order, OrderProduct, ShippingAddress};
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;


class StripeCheckoutController extends Controller
{
    /**
     * Create Stripe Checkout Session
     */
    public function createCheckoutSession(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'cart_id' => 'nullable|string|exists:cart_products,cart_id',
            'product_id' => 'nullable|integer|exists:products,id',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'address' => 'nullable|array',
            'address.first_name' => 'nullable|string',
            'address.last_name' => 'nullable|string',
            'address.email' => 'nullable|email',
            'address.phone_number' => 'nullable|string',
            'address.address' => 'nullable|string',
            'address.city' => 'nullable|string',
            'address.zip_code' => 'nullable|string',
            'address.state_id' => 'nullable|integer',
            'address.country_id' => 'nullable|integer',
        ]);

        Stripe::setApiKey(env('STRIPE_SECRET'));

        DB::beginTransaction();

        try {
            // ✅ Step 1: Ensure Stripe Customer exists
            $customerId = $user->stripe_customer_id;

            if (!$customerId) {
                $customer = \Stripe\Customer::create([
                    'email' => $user->email,
                    'name' => $user->name ?? ($user->first_name . ' ' . $user->last_name),
                    'metadata' => ['user_id' => $user->id],
                ]);

                $customerId = $customer->id;
                $user->stripe_customer_id = $customerId;
                $user->save();
            }

            // ✅ Step 2: Determine products to checkout
            $products = collect();
            $currency = Currency::where('status', 1)->value('code') ?? 'AED';

            if ($request->filled('product_id') && !$request->filled('cart_id')) {
                // Direct product checkout
                $product = Product::with('shop')->findOrFail($request->product_id);
                $products->push([
                    'product_id' => $product->id,
                    'name' => $product->slug,
                    'amount' => $product->price,
                    'quantity' => 1,
                    'shop_id' => $product->shop_id,
                ]);

            } elseif ($request->filled('cart_id')) {
                $cart = Cart::where('cart_id', $request->cart_id)->firstOrFail();
                $cartProducts = collect($cart->products_data);

                if ($request->filled('shop_id')) {
                    $filtered = $cartProducts->where('shop_id', $request->shop_id);
                } elseif ($request->filled('product_id')) {
                    $filtered = $cartProducts->where('product_id', $request->product_id);
                } else {
                    return response()->json(['message' => 'Please provide product_id or shop_id.'], 422);
                }

                if ($filtered->isEmpty()) {
                    return response()->json(['message' => 'No matching products found.'], 404);
                }

                $products = $filtered->map(fn($p) => [
                    'product_id' => $p['product_id'],
                    'name' => $p['product_name'],
                    'amount' => $p['product_price'],
                    'quantity' => $p['quantity'],
                    'shop_id' => $p['shop_id'],
                ])->values();
            } else {
                return response()->json(['message' => 'Invalid checkout scenario.'], 422);
            }

            // ✅ Step 3: Prepare Stripe line items
            $lineItems = [];
            foreach ($products as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $item['name'],
                        ],
                       'unit_amount' => max(1, intval(floatval($item['amount']) * 100)), // convert AED to fils
                    ],
                    'quantity' => $item['quantity'],
                ];
            }

            // ✅ Step 4: Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'customer' => $customerId,
                'line_items' => $lineItems,
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'order_checkout',
                    'cart_id' => $request->cart_id ?? null,
                    'shop_id' => $request->shop_id ?? null,
                    'product_ids' => $products->pluck('product_id')->implode(','),
                    'shipping_address' => json_encode($request->address ?? []),
                ],
                'success_url' => config('app.frontend_url') . '/order-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/checkout-cancelled',
            ]);

            DB::commit();

            return response()->json([
                'checkoutUrl' => $session->url,
                'sessionId' => $session->id,
                'currency' => $currency,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Stripe Checkout Failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create Stripe checkout session',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Stripe webhook events (when payment succeeds)
     */
    public function handleStripeWebhook(Request $request)
    {
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            return response('Invalid payload', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $metadata = $session->metadata ?? [];
            $userId = $metadata->user_id ?? null;
            $cartId = $metadata->cart_id ?? null;
            $productIds = collect(explode(',', $metadata->product_ids ?? ''))->filter();

            DB::beginTransaction();
            try {
                // Create order record
                $currency = Currency::where('status', 1)->value('code') ?? 'AED';
                $subtotal = 0;

                $products = Product::whereIn('id', $productIds)->with('shop')->get();
                foreach ($products as $product) {
                    $subtotal += $product->price;
                }

                $order = Order::create([
                    'buyer_id' => $userId,
                    'buyer_type' => 'customer',
                    'price_subtotal' => $subtotal,
                    'price_shipping' => 0,
                    'price_total' => $subtotal,
                    'price_currency' => $currency,
                    'status' => 1,
                    'payment_method' => 'stripe',
                    'payment_status' => 'paid',
                ]);

                $order->order_number = 10000 + $order->id;
                $order->save();

                foreach ($products as $product) {
                    OrderProduct::create([
                        'order_id' => $order->id,
                        'seller_id' => $product->user_id,
                        'buyer_id' => $userId,
                        'buyer_type' => 'customer',
                        'product_id' => $product->id,
                        'product_title' => $product->name,
                        'product_slug' => Str::slug($product->name),
                        'product_unit_price' => $product->price,
                        'product_quantity' => 1,
                        'product_currency' => $currency,
                        'product_total_price' => $product->price,
                        'product_type' => 'physical',
                        'order_status' => 'paid',
                    ]);
                }

                // ✅ Save Shipping Address (if provided)
                if (!empty($address)) {
                    ShippingAddress::create([
                        'user_id' => $userId,
                        'title' => ($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''),
                        'first_name' => $address['first_name'] ?? '',
                        'last_name' => $address['last_name'] ?? '',
                        'email' => $address['email'] ?? '',
                        'phone_number' => $address['phone_number'] ?? '',
                        'address' => $address['address'] ?? '',
                        'city' => $address['city'] ?? '',
                        'zip_code' => $address['zip_code'] ?? '',
                        'state_id' => $address['state_id'] ?? null,
                        'country_id' => $address['country_id'] ?? null,
                        'address_type' => 'shipping',
                    ]);
                }

                // ✅ Remove checked-out items from cart
                if ($cartId) {
                    $cart = Cart::where('cart_id', $cartId)->first();
                    if ($cart && !empty($cart->products_data)) {
                        $cart->products_data = collect($cart->products_data)
                            ->reject(fn($item) => in_array($item['product_id'], $productIds->toArray()))
                            ->values();
                        $cart->save();
                    }
                }

                DB::commit();
                \Log::info('✅ Stripe order created successfully for session: ' . $session->id);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('❌ Stripe order creation failed: ' . $e->getMessage());
            }
        }

        return response('Webhook handled', 200);
    }
}
