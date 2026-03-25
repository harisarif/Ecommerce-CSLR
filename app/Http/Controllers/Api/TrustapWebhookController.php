<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FcmHelper;
use App\Helpers\PusherHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\{Order, OrderProduct, User, Notification, PaymentTransfer, Shop, TrustapOauthState};

class TrustapWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();

        Log::info('Trustap Webhook Received', $data);

        $transactionId = $data['transaction']['id'] ?? null;
        $status = $data['transaction']['status'] ?? null;

        if (!$transactionId) {
            return response()->json(['ok' => true]);
        }

        $order = Order::where('trustap_transaction_id', $transactionId)->first();

        if (!$order) {
            Log::warning('Order not found for Trustap transaction', [
                'transaction_id' => $transactionId
            ]);
            return response()->json(['ok' => true]);
        }

        // Prevent duplicate processing
        if ($status === 'paid' && $order->payment_status === 'paid') {
            return response()->json(['success' => true]);
        }

        switch ($status) {

            /*
            |--------------------------------------------------------------------------
            | PAYMENT COMPLETED
            |--------------------------------------------------------------------------
            */

            case 'paid':

                $order->update([
                    'payment_status' => 'paid',
                    'trustap_status' => 'paid'
                ]);

                $products = OrderProduct::where('order_id', $order->id)->get();

                if ($products->isEmpty()) {
                    Log::warning('Order has no products', ['order_id' => $order->id]);
                    return response()->json(['success' => true]);
                }

                $currency = strtoupper($order->price_currency ?? 'AED');

                /*
                |--------------------------------------------------------------------------
                | Save PaymentTransfer (Trustap Escrow)
                |--------------------------------------------------------------------------
                */

                try {

                    if (!PaymentTransfer::where('order_id', $order->id)->exists()) {

                        PaymentTransfer::create([
                            'order_id' => $order->id,
                            'shop_id' => $products->first()->shop_id,

                            'trustap_transaction_id' => $transactionId,

                            'checkout_amount_cents' => $order->total_amount * 100,
                            'checkout_currency' => $currency,

                            'gross_amount_cents' => $order->total_amount * 100,
                            'stripe_fee_cents' => 0,
                            'net_amount_cents' => $order->total_amount * 100,

                            'amount_cents' => $order->total_amount * 100,
                            'platform_fee_cents' => 0,
                            'currency' => $currency,

                            'status' => 'on_hold',
                            'release_at' => now(),

                            'meta' => [
                                'provider' => 'trustap',
                                'transaction_id' => $transactionId
                            ]
                        ]);
                    }

                } catch (\Exception $e) {

                    Log::error('PaymentTransfer creation failed', [
                        'error' => $e->getMessage(),
                        'order_id' => $order->id
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | SELLER NOTIFICATIONS
                |--------------------------------------------------------------------------
                */

                try {

                    $buyer = User::find($order->buyer_id);
                    $buyerShopName = $buyer?->shop?->name ?? ($buyer?->username ?? 'A buyer');

                    foreach ($products as $product) {

                        $seller = User::find($product->seller_id);
                        $shop = $seller?->shop;

                        $price = $product->product_unit_price;

                        $notificationText = "{$buyerShopName} purchased your product \"{$product->product_title}\" at price {$price} {$currency}";

                        if ($seller) {

                            Notification::create([
                                'type' => 'order',
                                'notifiable_type' => get_class($seller),
                                'notifiable_id' => $seller->id,
                                'data' => [
                                    'title' => 'Product Purchased',
                                    'body' => $notificationText,
                                    'order_id' => $order->id,
                                    'product_id' => $product->product_id,
                                    'price' => $price,
                                    'currency' => $currency,
                                    'buyer_id' => $buyer?->id,
                                    'buyer_shop_name' => $buyerShopName,
                                    'shop' => [
                                        'id' => $shop?->id,
                                        'name' => $shop?->name,
                                        'slug' => $shop?->slug,
                                        'image' => $shop?->image_url,
                                    ],
                                ],
                            ]);

                            PusherHelper::trigger(
                                "private-notifications.{$seller->id}",
                                'new-notification',
                                [
                                    'title' => 'Product Purchased',
                                    'body' => $notificationText,
                                    'type' => 'order',
                                    'order_id' => $order->id,
                                    'product_id' => $product->product_id,
                                    'price' => $price,
                                    'currency' => $currency,
                                    'shop' => [
                                        'id' => $shop?->id,
                                        'name' => $shop?->name,
                                        'slug' => $shop?->slug,
                                        'image' => $shop?->image_url,
                                    ],
                                    'buyer' => [
                                        'id' => $buyer?->id,
                                        'name' => $buyerShopName,
                                    ],
                                ]
                            );

                            if (!empty($seller->fcm_token)) {

                                FcmHelper::send(
                                    $seller,
                                    'Product Purchased',
                                    $notificationText,
                                    [
                                        'type' => 'order',
                                        'order_id' => $order->id,
                                        'product' => [
                                            'id' => $product->product_id,
                                            'slug' => $product->product_title,
                                            'price' => $price,
                                        ],
                                        'buyer' => [
                                            'id' => $buyer?->id,
                                            'shop_name' => $buyerShopName,
                                        ],
                                        'shop' => [
                                            'id' => $shop?->id,
                                            'name' => $shop?->name,
                                            'slug' => $shop?->slug,
                                        ],
                                    ],
                                    $order->id,
                                    'order'
                                );
                            }

                        }
                    }

                } catch (\Exception $e) {

                    Log::error('Seller purchase notification failed', [
                        'error' => $e->getMessage(),
                        'order_id' => $order->id ?? null,
                    ]);
                }

            break;


            /*
            |--------------------------------------------------------------------------
            | SELLER SHIPPED
            |--------------------------------------------------------------------------
            */

            case 'shipped':

                $order->update([
                    'trustap_status' => 'shipped'
                ]);

            break;


            /*
            |--------------------------------------------------------------------------
            | ESCROW RELEASED
            |--------------------------------------------------------------------------
            */

            case 'completed':

                $order->update([
                    'trustap_status' => 'released'
                ]);

                PaymentTransfer::where('order_id', $order->id)
                    ->update([
                        'status' => 'released'
                    ]);

            break;

        }

        return response()->json(['success' => true]);
    }


    public function trustapCallback(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'state'=> 'required|string',
        ]);

        $stateRecord = TrustapOauthState::where('state', $request->state)->firstOrFail();

        $user = User::findOrFail($stateRecord->user_id);

        // Exchange code for access token
        $response = \Http::asForm()->post('https://sso.trustap.com/auth/realms/trustap-stage/protocol/openid-connect/token', [
            'client_id'     => config('services.trustap.client_id'),
            'client_secret' => config('services.trustap.secret'),
            'grant_type'    => 'authorization_code',
            'code'          => $request->code,
            'redirect_uri'  => config('app.frontend_url') . '/api/v1/trustap/callback',
        ]);

        $data = $response->json();

        if (!isset($data['id_token'])) {
            return response()->json(['message' => 'Failed to get Trustap ID token'], 400);
        }

        // Decode id_token JWT
        $payload = explode('.', $data['id_token'])[1];
        $payload = json_decode(base64_decode($payload), true);

        $trustapUserId = $payload['sub'] ?? null;

        if ($trustapUserId) {
            $user->update([
                'trustap_oauth_user_id'   => $trustapUserId,
                'trustap_access_token'    => $data['access_token'] ?? null,
                'trustap_refresh_token'   => $data['refresh_token'] ?? null,
                'trustap_token_expires_at'=> isset($data['expires_in'])
                                            ? now()->addSeconds($data['expires_in'])
                                            : null,
            ]);
        }

        // Delete state record
        $stateRecord->delete();

        return response()->json([
            'message'          => 'Trustap account linked successfully',
            'trustap_user_id'  => $trustapUserId,
            'access_token'     => $data['access_token'],
            'refresh_token'    => $data['refresh_token'],
        ]);
    }
}