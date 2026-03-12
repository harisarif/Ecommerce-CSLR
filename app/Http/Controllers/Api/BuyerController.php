<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\PaymentTransfer;
use App\Helpers\FcmHelper;
use App\Helpers\PusherHelper;
use App\Models\Notification;
use App\Services\TrustapService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyerController extends Controller
{
    protected $trustap;

    public function __construct(TrustapService $trustap)
    {
        $this->trustap = $trustap;
    }

    public function confirmDelivery(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        // Ensure only buyer can confirm
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->trustap_status !== 'shipped') {
            return response()->json(['message' => 'Order is not shipped'], 422);
        }

        DB::beginTransaction();
        try {
            // Call Trustap API to confirm delivery
            $response = $this->trustap->confirmDelivery($order->trustap_transaction_id, $order->buyer->trustap_user_id);

            // Check Trustap response
            if (!isset($response['status']) || !in_array($response['status'], ['delivered', 'released'])) {
                $errorMessage = $response['error'] ?? $response['message'] ?? 'Trustap delivery confirmation failed';
                throw new \Exception($errorMessage);
            }

            // Update local order status
            $order->update([
                'trustap_status' => 'released'
            ]);

            // Update PaymentTransfer
            PaymentTransfer::where('order_id', $order->id)
                ->update(['status' => 'released']);

            // Notify seller (unchanged)
            foreach ($order->orderProducts as $product) {

                $seller = $product->seller; // may be null
                $buyer = $order->buyer;
                $buyerShopName = $buyer?->shop?->name ?? ($buyer?->username ?? 'A buyer');
                $shop = $seller?->shop;

                if (!$seller) continue; // skip if no seller

                $notificationText = "{$buyerShopName} confirmed delivery for \"{$product->product_title}\"";

                Notification::create([
                    'type' => 'order',
                    'notifiable_type' => get_class($seller),
                    'notifiable_id' => $seller->id,
                    'data' => [
                        'title' => 'Delivery Confirmed',
                        'body' => $notificationText,
                        'order_id' => $order->id,
                        'product_id' => $product->product_id,
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

                // Pusher
                PusherHelper::trigger(
                    "private-notifications.{$seller->id}",
                    'new-notification',
                    [
                        'title' => 'Delivery Confirmed',
                        'body' => $notificationText,
                        'type' => 'order',
                        'order_id' => $order->id,
                        'product_id' => $product->product_id,
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

                // FCM
                if (!empty($seller->fcm_token)) {
                    FcmHelper::send(
                        $seller,
                        'Delivery Confirmed',
                        $notificationText,
                        [
                            'type' => 'order',
                            'order_id' => $order->id,
                            'product' => [
                                'id' => $product->product_id,
                                'slug' => $product->product_title,
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

            DB::commit();

            return response()->json(['message' => 'Delivery confirmed successfully']);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log with actual Trustap error
            \Log::error('Buyer confirm delivery failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Return actual Trustap error to API response
            return response()->json(['message' => 'Confirmation failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function buyerOrders()
    {
        $user = auth()->user();

        $orders = Order::where('buyer_id', $user->id)
            ->with(['orderProducts.product.shop'])
            ->latest()
            ->get();

        $formattedOrders = $orders->map(function ($order) {

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total_price' => $order->price_total,
                'currency' => $order->price_currency,
                'payment_status' => $order->payment_status,
                'trustap_transaction_id' => $order->trustap_transaction_id,
                'trustap_status' => $order->trustap_status,
                'created_at' => $order->created_at,

                'products' => $order->orderProducts->map(function ($op) {

                    return [
                        'order_product_id' => $op->id,
                        'product_id' => $op->product_id,
                        'product_name' => $op->product_title,
                        'product_image' => $op->product->main_image ?? null,
                        'price' => $op->product_total_price,
                        'quantity' => $op->product_quantity,

                        'shop' => [
                            'shop_id' => $op->product->shop->id ?? null,
                            'shop_name' => $op->product->shop->name ?? null,
                            'shop_image' => $op->product->shop->image_url ?? null,
                        ]
                    ];
                })
            ];
        });

        return response()->json([
            'type' => 'buyer_orders',
            'orders' => $formattedOrders
        ]);
    }

    public function buyerOrderDetail($id)
    {
        $userId = auth()->id();

        $order = Order::where('buyer_id', $userId)
            ->with([
                'buyer',
                'orderProducts.product.shop',
                'orderProducts.product.mainImageRelation'
            ])
            ->findOrFail($id);

        $order->orderProducts->transform(function ($op) {

            $meta = json_decode($op->meta ?? '{}', true);

            $op->offer_id = $meta['offer_id'] ?? null;

            $op->product_name = $op->product->details()->first()->title ?? $op->product_title;

            $op->product_image = $op->product->main_image ?? null;

            $op->shop = $op->product->shop ?? null;

            return $op;
        });

        return response()->json([
            'type' => 'buyer_order_detail',
            'order' => $order
        ]);
    }
}