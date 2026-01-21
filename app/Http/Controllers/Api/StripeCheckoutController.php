<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\{Cart, Product, Currency, Offer, OfferCounter, Order, OrderProduct, PaymentTransfer, ShippingAddress, Shop};
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;

class StripeCheckoutController extends Controller
{
    /**
     * Create Stripe Checkout Session
     */
    // public function createCheckoutSession(Request $request)
    // {
    //     $user = $request->user();

    //     $request->validate([
    //         'cart_id' => 'nullable|string|exists:cart_products,cart_id',
    //         'product_id' => 'nullable|integer|exists:products,id',
    //         'shop_id' => 'nullable|integer|exists:shops,id',
    //         'offer_id' => 'nullable|integer|exists:offers,id',
    //         'address' => 'nullable|array',
    //         'address.first_name' => 'nullable|string',
    //         'address.last_name' => 'nullable|string',
    //         'address.email' => 'nullable|email',
    //         'address.phone_number' => 'nullable|string',
    //         'address.address' => 'nullable|string',
    //         'address.city' => 'nullable|string',
    //         'address.zip_code' => 'nullable|string',
    //         'address.state_id' => 'nullable|integer',
    //         'address.country_id' => 'nullable|integer',
    //     ]);
    //     Stripe::setApiKey(env('STRIPE_SECRET'));

    //     DB::beginTransaction();

    //     try {
    //         // ✅ Step 1: Ensure Stripe Customer exists
    //         $customerId = $user->stripe_customer_id;

    //         if (!$customerId) {
    //             $customer = \Stripe\Customer::create([
    //                 'email' => $user->email,
    //                 'name' => $user->name ?? ($user->first_name . ' ' . $user->last_name),
    //                 'metadata' => ['user_id' => $user->id],
    //             ]);

    //             $customerId = $customer->id;
    //             $user->stripe_customer_id = $customerId;
    //             $user->save();
    //         }

    //         // ✅ Step 2: Determine products to checkout
    //         $products = collect();
    //         $currency = Currency::where('status', 1)->value('code') ?? 'AED';

    //         if ($request->filled('offer_id')) {

    //             // Auto-load offer + product
    //             $offer = Offer::with('product')->findOrFail($request->offer_id);
    //             $product = $offer->product;

    //             if (!$product) {
    //                 return response()->json(['message' => 'Offer product not found'], 404);
    //             }

    //             // Auto-get latest counter OR base offer
    //             $latestCounter = OfferCounter::where('offer_id', $offer->id)
    //                 ->orderByDesc('id')
    //                 ->first();

    //             $price = $latestCounter ? $latestCounter->price : $offer->price;
    //             $counterId = $latestCounter ? $latestCounter->id : null;

    //             // Build automatic product data (no need product_id/shop_id from request)
    //             $products->push([
    //                 'product_id' => $product->id,
    //                 'name' => $product->slug ?? ($product->details()->first()->title ?? 'Product'),
    //                 'amount' => $price,
    //                 'quantity' => 1,
    //                 'shop_id' => $product->shop_id,
    //                 'offer_id' => $offer->id,
    //                 'offer_counter_id' => $counterId,
    //             ]);
    //         }

    //         // direct product checkout (single product) without offer
    //         elseif ($request->filled('product_id') && !$request->filled('cart_id')) {
    //             // Direct product checkout
    //             $product = Product::with('shop')->findOrFail($request->product_id);
    //             $products->push([
    //                 'product_id' => $product->id,
    //                 'name' => $product->slug,
    //                 'amount' => $product->price,
    //                 'quantity' => 1,
    //                 'shop_id' => $product->shop_id,
    //             ]);

    //         } elseif ($request->filled('cart_id')) {
    //             $cart = Cart::where('cart_id', $request->cart_id)->firstOrFail();
    //             $cartProducts = collect($cart->products_data);

    //             if ($request->filled('shop_id')) {
    //                 $filtered = $cartProducts->where('shop_id', $request->shop_id);
    //             } elseif ($request->filled('product_id')) {
    //                 $filtered = $cartProducts->where('product_id', $request->product_id);
    //             } else {
    //                 return response()->json(['message' => 'Please provide product_id or shop_id.'], 422);
    //             }

    //             if ($filtered->isEmpty()) {
    //                 return response()->json(['message' => 'No matching products found.'], 404);
    //             }

    //             $products = $filtered->map(fn($p) => [
    //                 'product_id' => $p['product_id'],
    //                 'name' => $p['product_name'],
    //                 'amount' => $p['product_price'],
    //                 'quantity' => $p['quantity'],
    //                 'shop_id' => $p['shop_id'],
    //             ])->values();
    //         } else {
    //             return response()->json(['message' => 'Invalid checkout scenario.'], 422);
    //         }


    //         // ✅ Step 3: Prepare Stripe line items
    //         $lineItems = [];
    //         $totalAmountCents = 0;

    //         foreach ($products as $item) {
    //             $unitAmountCents = max(1, intval(floatval($item['amount']) * 100));
    //             $lineItems[] = [
    //                 'price_data' => [
    //                     'currency' => strtolower($currency),
    //                     'product_data' => ['name' => $item['name']],
    //                     'unit_amount' => $unitAmountCents,
    //                 ],
    //                 'quantity' => $item['quantity'],
    //             ];
    //             $totalAmountCents += $unitAmountCents * intval($item['quantity']);
    //         }

    //         // Calculate platform fee 5%
    //         $platformFee = intval($totalAmountCents * 0.05);

    //         $firstShop = $products->first();
    //         $shop = Shop::find($firstShop['shop_id']);

    //         if (!$shop || !$shop->stripe_account_id) {
    //             return response()->json(['message' => 'Shop not connected to Stripe'], 422);
    //         }
    //         // Build metadata - include product ids and (optionally) single offer info
    //         $metadata = [
    //             'user_id' => (string) $user->id,
    //             'type' => 'order_checkout',
    //             'cart_id' => (string) ($request->cart_id ?? ''),
    //             'shop_id' => (string) ($products->first()['shop_id'] ?? ''),
    //             'product_ids' => $products->pluck('product_id')->implode(','), // CSV string
    //             'shipping_address' => $request->address ? json_encode($request->address, JSON_UNESCAPED_SLASHES) : '',
    //             'offer_id' => $request->filled('offer_id') ? (string) $request->offer_id : '',
    //             'offer_counter_id' => $request->filled('offer_id') ? (string) ($products->first()['offer_counter_id'] ?? '') : '',
    //         ];

    //         // Create Stripe Checkout Session
    //         $session = Session::create([
    //             'payment_method_types' => ['card'],
    //             'mode' => 'payment',
    //             'customer' => $customerId,
    //             'line_items' => $lineItems,
    //             'metadata' => $metadata,
    //             'success_url' => config('app.frontend_url') . '/order-success?session_id={CHECKOUT_SESSION_ID}',
    //             'cancel_url' => config('app.frontend_url') . '/checkout-cancelled',
    //             'payment_intent_data' => [
    //                 'application_fee_amount' => $platformFee, // 5% platform fee
    //                 'transfer_data' => [
    //                     'destination' => $shop->stripe_account_id, // remaining 95% to shop
    //                 ],
    //             ],
    //         ]);

    //         PaymentTransfer::create([
    //             'order_id' => null,
    //             'shop_id' => $shop->id,
    //             'payment_intent_id' => 'pi_test_' . \Illuminate\Support\Str::random(8),
    //             'charge_id' => 'ch_test_' . \Illuminate\Support\Str::random(8),
    //             'amount_cents' => $totalAmountCents,
    //             'platform_fee_cents' => $platformFee,
    //             'currency' => strtolower($currency),
    //             'status' => 'on_hold',
    //             'release_at' => now()->addDays(7),
    //             'meta' => [
    //                 'checkout_session_id' => $session->id,
    //                 'test_mode' => true,
    //             ],
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'checkoutUrl' => $session->url,
    //             'sessionId' => $session->id,
    //             'currency' => $currency,
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Stripe Checkout Failed', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Failed to create Stripe checkout session',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function createCheckoutSession(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'cart_id' => 'nullable|string|exists:cart_products,cart_id',
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

            if ($request->filled('offer_id')) {

                // Auto-load offer + product
                $offer = Offer::with('product')->findOrFail($request->offer_id);
                $product = $offer->product;

                if (!$product) {
                    return response()->json(['message' => 'Offer product not found'], 404);
                }

                // Auto-get latest counter OR base offer
                $latestCounter = OfferCounter::where('offer_id', $offer->id)
                    ->orderByDesc('id')
                    ->first();

                $price = $latestCounter ? $latestCounter->price : $offer->price;
                $counterId = $latestCounter ? $latestCounter->id : null;

                // Build automatic product data (no need product_id/shop_id from request)
                $products->push([
                    'product_id' => $product->id,
                    'name' => $product->slug ?? ($product->details()->first()->title ?? 'Product'),
                    'amount' => $price,
                    'quantity' => 1,
                    'shop_id' => $product->shop_id,
                    'offer_id' => $offer->id,
                    'offer_counter_id' => $counterId,
                ]);
            }

            // direct product checkout (single product) without offer
            elseif ($request->filled('product_id') && !$request->filled('cart_id')) {
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
            $totalAmountCents = 0;

            foreach ($products as $item) {
                $unitAmountCents = max(1, intval(floatval($item['amount']) * 100));
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => ['name' => $item['name']],
                        'unit_amount' => $unitAmountCents,
                    ],
                    'quantity' => $item['quantity'],
                ];
                $totalAmountCents += $unitAmountCents * intval($item['quantity']);
            }

            // Calculate platform fee 5%
            $platformFee = intval($totalAmountCents * 0.05);

            $firstShop = $products->first();
            $shop = Shop::find($firstShop['shop_id']);

            if (!$shop || !$shop->stripe_account_id) {
                return response()->json(['message' => 'Shop not connected to Stripe'], 422);
            }
            // Build metadata - include product ids and (optionally) single offer info
            $metadata = [
                'user_id' => (string) $user->id,
                'type' => 'order_checkout',
                'cart_id' => (string) ($request->cart_id ?? ''),
                'shop_id' => (string) ($products->first()['shop_id'] ?? ''),
                'product_ids' => $products->pluck('product_id')->implode(','), // CSV string
                'shipping_address' => $request->address ? json_encode($request->address, JSON_UNESCAPED_SLASHES) : '',
                'offer_id' => $request->filled('offer_id') ? (string) $request->offer_id : '',
                'offer_counter_id' => $request->filled('offer_id') ? (string) ($products->first()['offer_counter_id'] ?? '') : '',
            ];

            // Create Stripe Checkout Session
            $session = Session::create([
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'customer' => $customerId,
                'line_items' => $lineItems,
                'metadata' => $metadata,
                'success_url' => config('app.frontend_url') . '/order-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/checkout-cancelled',

                // 👇 PLATFORM OWNS THE CHARGE
                'payment_intent_data' => [
                    'capture_method' => 'automatic',
                ],
            ]);

            DB::commit();

            return response()->json([
                'checkoutUrl' => $session->url,
                'sessionId' => $session->id,
                'currency' => $currency,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stripe Checkout Failed', ['error' => $e->getMessage()]);
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
        Log::info('🔥 Stripe webhook endpoint hit');
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $request->getContent();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response('Invalid payload', 400);
        }

        Log::info('📩 Stripe event received', [
            'type' => $event->type,
        ]);

        // Only handle completed checkout sessions
        if ($event->type === 'checkout.session.completed') {

            $session = $event->data->object;
            $metadata = $session->metadata;
            $userId = $metadata['user_id'] ?? null;
            $offerId = $metadata['offer_id'] ?? null;
            $cartId = $metadata['cart_id'] ?? null;
            
            $productIds = collect(explode(',', $metadata['product_ids'] ?? ''))->filter();

            // Retrieve payment intent and transfer id
            $paymentIntentId = $session->payment_intent;
            // $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            $paymentIntent = \Stripe\PaymentIntent::retrieve(
                $paymentIntentId,
                ['expand' => ['charges.data']]
            );

            // transfer id (Stripe automatically creates a transfer to connected account)
            $amountCents = $paymentIntent->amount_received ?? 0;


            DB::beginTransaction();
            try {
                $currency = Currency::where('status', 1)->value('code') ?? 'AED';
                $subtotal = 0;

                // Retrieve product models
                $products = Product::whereIn('id', $productIds)->with('shop')->get();

                if ($products->isEmpty()) {
                    Log::error('❌ No products found for PaymentTransfer', [
                        'metadata' => $metadata,
                    ]);
                    DB::rollBack();
                    return response('No products', 200);
                }

                // If metadata has offer_id => single-offer checkout; compute price accordingly
                $offerCounterId = $metadata['offer_counter_id'] ?? null;

                // If offer_id exists, try to get the Offer and the final price
                $offer = null;
                $finalPricesByProductId = []; // product_id => price (float)

                if ($offerId) {
                    $offer = Offer::with('product')->find($offerId);
                    if ($offer) {
                        $offer->is_paid = 1;
                        $offer->save();
                        // Prefer the specific counter id from metadata if present
                        if (!empty($offerCounterId)) {
                            $counter = OfferCounter::find($offerCounterId);
                        } else {
                            // else pick latest counter
                            $counter = OfferCounter::where('offer_id', $offer->id)
                                ->orderByDesc('id')
                                ->first();
                        }

                        $finalPrice = $counter ? floatval($counter->price) : floatval($offer->price);
                        // map to the offer's product id
                        $finalPricesByProductId[$offer->product_id] = $finalPrice;
                    }
                }

                // If no offer-based override, use product->price
                foreach ($products as $product) {
                    if (!isset($finalPricesByProductId[$product->id])) {
                        $finalPricesByProductId[$product->id] = floatval($product->price);
                    }
                }

                // Sum subtotal using finalPrices
                foreach ($finalPricesByProductId as $pid => $price) {
                    $subtotal += $price;
                }

                // Create order
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


                // 3️⃣ HOLD PAYMENT (PaymentTransfer)
                $charge = $paymentIntent->charges->data[0] ?? null;
                Log::info('🟡 Creating PaymentTransfer', [
                    'order_id' => $order->id,
                    'amount' => $paymentIntent->amount_received,
                ]);
                $balanceTransaction = \Stripe\BalanceTransaction::retrieve(
                    $charge->balance_transaction
                );

                $platformFeePercent = floatval(env('PLATFORM_FEE_PERCENT', 5));
                $platformFeeCents = intval(
                    round($balanceTransaction->amount * ($platformFeePercent / 100))
                );

                $netToShopCents = $balanceTransaction->net - $platformFeeCents;
                PaymentTransfer::create([
                    'order_id' => $order->id,
                    'shop_id' => $products->first()->shop_id,

                    'payment_intent_id' => $paymentIntent->id,
                    'charge_id' => $charge->id,

                    // Checkout info (display)
                    'checkout_amount_cents' => $paymentIntent->amount_received, // AED
                    'checkout_currency' => $paymentIntent->currency,            // AED

                    // Stripe balance (REAL)
                    'gross_amount_cents' => $balanceTransaction->amount,        // USD
                    'stripe_fee_cents' => abs($balanceTransaction->fee),         // USD
                    'platform_fee_cents' => $platformFeeCents,                  // USD
                    'net_amount_cents' => $netToShopCents,                       // USD
                    'settlement_currency' => $balanceTransaction->currency,     // USD

                    'exchange_rate' => $balanceTransaction->exchange_rate,

                    'status' => 'on_hold',
                    'release_at' => now()->addDays(7),

                    'meta' => [
                        'stripe_session_id' => $session->id,
                        'customer_id' => $session->customer,
                        'cart_id' => $cartId,
                        'offer_id' => $offerId,
                    ],
                ]);
                Log::info('🟢 PaymentTransfer created successfully');

                // Create OrderProducts using finalPricesByProductId mapping
                foreach ($products as $product) {
                    $priceToUse = $finalPricesByProductId[$product->id] ?? floatval($product->price);
                    OrderProduct::create([
                        'order_id' => $order->id,
                        'seller_id' => $product->user_id,
                        'buyer_id' => $userId,
                        'buyer_type' => 'customer',
                        'product_id' => $product->id,
                        'product_title' => $product->slug ?? ($product->details()->first()->title ?? 'Product'),
                        'product_slug' => Str::slug($product->slug ?? ($product->details()->first()->title ?? 'product')),
                        'product_unit_price' => $priceToUse,
                        'product_quantity' => 1,
                        'product_currency' => $currency,
                        'product_total_price' => $priceToUse,
                        'product_type' => 'physical',
                        'order_status' => 'paid',
                        // optional: store associated offer info for traceability
                        'meta' => json_encode([
                            'offer_id' => $offerId ?? null,
                            'offer_counter_id' => $offerCounterId ?? null,
                            'stripe_session_id' => $session->id ?? null,
                        ]),
                    ]);
                }

                // Save shipping address if provided
                $address = [];
                if (!empty($metadata['shipping_address'])) {
                    $address = json_decode($metadata['shipping_address'], true);
                }

                if (!empty($address) && is_array($address)) {
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

                // Remove checked-out items from cart if provided
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
                Log::info('✅ Stripe order created successfully for session: ' . ($session->id ?? 'unknown'));
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('❌ Stripe order creation failed: ' . $e->getMessage(), [
                    'exception' => $e,
                    'session' => $session ?? null
                ]);
            }
        }

        return response('Webhook handled', 200);
    }


    // public function handleStripeWebhook(Request $request)
    // {
        
    //     $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
    //     $payload = $request->getContent();
    //     $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    //     $event = null;

    //     try {
    //         $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    //     } catch (\Exception $e) {
    //         Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
    //         return response('Invalid payload', 400);
    //     }

    //     // Only handle completed checkout sessions
    //     if ($event->type === 'checkout.session.completed') {

    //         $session = $event->data->object;
    //         $metadata = $session->metadata;
    //         $userId = $metadata['user_id'] ?? null;
    //         $offerId = $metadata['offer_id'] ?? null;
    //         $cartId = $metadata['cart_id'] ?? null;
            
    //         $productIds = collect(explode(',', $metadata['product_ids'] ?? ''))->filter();

    //         // Retrieve payment intent and transfer id
    //         $paymentIntentId = $session->payment_intent;
    //         $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    //         // transfer id (Stripe automatically creates a transfer to connected account)
    //         $transferId = $paymentIntent->charges->data[0]->transfer ?? null;
    //         $amountCents = $paymentIntent->amount_received ?? 0;
    //         $currency = $paymentIntent->currency ?? 'aed';

    //         PaymentTransfer::create([
    //             'order_id' => $order->id ?? null,
    //             'shop_id' => $shop->id ?? null,
    //             'stripe_transfer_id' => $transferId,
    //             'amount_cents' => $amountCents,
    //             'platform_fee_cents' => $paymentIntent->application_fee_amount ?? 0,
    //             'currency' => $currency,
    //             'status' => 'paid',
    //             'meta' => [
    //                 'stripe_session_id' => $session->id,
    //                 'stripe_customer_id' => $session->customer,
    //             ],
    //         ]);

    //         DB::beginTransaction();
    //         try {
    //             $currency = Currency::where('status', 1)->value('code') ?? 'AED';
    //             $subtotal = 0;

    //             // Retrieve product models
    //             $products = Product::whereIn('id', $productIds)->with('shop')->get();

    //             // If metadata has offer_id => single-offer checkout; compute price accordingly
    //             $offerCounterId = $metadata['offer_counter_id'] ?? null;

    //             // If offer_id exists, try to get the Offer and the final price
    //             $offer = null;
    //             $finalPricesByProductId = []; // product_id => price (float)

    //             if ($offerId) {
    //                 $offer = Offer::with('product')->find($offerId);
    //                 if ($offer) {
    //                     $offer->is_paid = 1;
    //                     $offer->save();
    //                     // Prefer the specific counter id from metadata if present
    //                     if (!empty($offerCounterId)) {
    //                         $counter = OfferCounter::find($offerCounterId);
    //                     } else {
    //                         // else pick latest counter
    //                         $counter = OfferCounter::where('offer_id', $offer->id)
    //                             ->orderByDesc('id')
    //                             ->first();
    //                     }

    //                     $finalPrice = $counter ? floatval($counter->price) : floatval($offer->price);
    //                     // map to the offer's product id
    //                     $finalPricesByProductId[$offer->product_id] = $finalPrice;
    //                 }
    //             }

    //             // If no offer-based override, use product->price
    //             foreach ($products as $product) {
    //                 if (!isset($finalPricesByProductId[$product->id])) {
    //                     $finalPricesByProductId[$product->id] = floatval($product->price);
    //                 }
    //             }

    //             // Sum subtotal using finalPrices
    //             foreach ($finalPricesByProductId as $pid => $price) {
    //                 $subtotal += $price;
    //             }

    //             // Create order
    //             $order = Order::create([
    //                 'buyer_id' => $userId,
    //                 'buyer_type' => 'customer',
    //                 'price_subtotal' => $subtotal,
    //                 'price_shipping' => 0,
    //                 'price_total' => $subtotal,
    //                 'price_currency' => $currency,
    //                 'status' => 1,
    //                 'payment_method' => 'stripe',
    //                 'payment_status' => 'paid',
    //             ]);

    //             $order->order_number = 10000 + $order->id;
    //             $order->save();

    //             // Create OrderProducts using finalPricesByProductId mapping
    //             foreach ($products as $product) {
    //                 $priceToUse = $finalPricesByProductId[$product->id] ?? floatval($product->price);
    //                 OrderProduct::create([
    //                     'order_id' => $order->id,
    //                     'seller_id' => $product->user_id,
    //                     'buyer_id' => $userId,
    //                     'buyer_type' => 'customer',
    //                     'product_id' => $product->id,
    //                     'product_title' => $product->slug ?? ($product->details()->first()->title ?? 'Product'),
    //                     'product_slug' => Str::slug($product->slug ?? ($product->details()->first()->title ?? 'product')),
    //                     'product_unit_price' => $priceToUse,
    //                     'product_quantity' => 1,
    //                     'product_currency' => $currency,
    //                     'product_total_price' => $priceToUse,
    //                     'product_type' => 'physical',
    //                     'order_status' => 'paid',
    //                     // optional: store associated offer info for traceability
    //                     'meta' => json_encode([
    //                         'offer_id' => $offerId ?? null,
    //                         'offer_counter_id' => $offerCounterId ?? null,
    //                         'stripe_session_id' => $session->id ?? null,
    //                     ]),
    //                 ]);
    //             }

    //             // Save shipping address if provided
    //             $address = [];
    //             if (!empty($metadata['shipping_address'])) {
    //                 $address = json_decode($metadata['shipping_address'], true);
    //             }

    //             if (!empty($address) && is_array($address)) {
    //                 ShippingAddress::create([
    //                     'user_id' => $userId,
    //                     'title' => ($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''),
    //                     'first_name' => $address['first_name'] ?? '',
    //                     'last_name' => $address['last_name'] ?? '',
    //                     'email' => $address['email'] ?? '',
    //                     'phone_number' => $address['phone_number'] ?? '',
    //                     'address' => $address['address'] ?? '',
    //                     'city' => $address['city'] ?? '',
    //                     'zip_code' => $address['zip_code'] ?? '',
    //                     'state_id' => $address['state_id'] ?? null,
    //                     'country_id' => $address['country_id'] ?? null,
    //                     'address_type' => 'shipping',
    //                 ]);
    //             }

    //             // Remove checked-out items from cart if provided
    //             if ($cartId) {
    //                 $cart = Cart::where('cart_id', $cartId)->first();
    //                 if ($cart && !empty($cart->products_data)) {
    //                     $cart->products_data = collect($cart->products_data)
    //                         ->reject(fn($item) => in_array($item['product_id'], $productIds->toArray()))
    //                         ->values();
    //                     $cart->save();
    //                 }
    //             }

    //             DB::commit();
    //             Log::info('✅ Stripe order created successfully for session: ' . ($session->id ?? 'unknown'));
    //         } catch (\Exception $e) {
    //             DB::rollBack();
    //             Log::error('❌ Stripe order creation failed: ' . $e->getMessage(), [
    //                 'exception' => $e,
    //                 'session' => $session ?? null
    //             ]);
    //         }
    //     }

    //     return response('Webhook handled', 200);
    // }
}
