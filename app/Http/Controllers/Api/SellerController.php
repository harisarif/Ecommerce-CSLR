<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrustapService;
use Illuminate\Http\Request;


class SellerController extends Controller
{
   protected $trustap;

    public function __construct(TrustapService $trustap)
    {
        $this->trustap = $trustap;
    }
    public function addTracking(Request $request, $orderId)
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'carrier' => 'required|string'
        ]);

        $order = Order::findOrFail($orderId);

        $seller = auth()->user(); // seller user

        $this->trustap->addTracking(
            $order->trustap_transaction_id,
            $seller->trustap_oauth_user_id,
            $request->carrier,
            $request->tracking_number
        );

        $order->update([
            'tracking_number' => $request->tracking_number,
            'trustap_status' => 'shipped'
        ]);

        return response()->json([
            'message' => 'Tracking added successfully'
        ]);
    }


    public function sellerOrders()
    {
        $sellerId = auth()->id();

        $orders = Order::whereHas('orderProducts', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->where('trustap_status', '!=', 'created')
            ->with([
                'buyer',
                'orderProducts.product.shop'
            ])
            ->latest()
            ->get();

        $formattedOrders = $orders->map(function ($order) use ($sellerId) {

            $products = $order->orderProducts
                ->where('seller_id', $sellerId)
                ->values()
                ->map(function ($op) {

                    $meta = json_decode($op->meta ?? '{}', true);

                    return [
                        'order_product_id' => $op->id,
                        'product_id' => $op->product_id,
                        'product_name' => $op->product->details()->first()->title ?? $op->product_title,
                        'product_image' => $op->product->main_image ?? null,
                        'price' => $op->product_total_price,
                        'quantity' => $op->product_quantity,

                        'offer_id' => $meta['offer_id'] ?? null,
                        'offer_counter_id' => $meta['offer_counter_id'] ?? null,

                        'shipping_tracking_number' => $op->shipping_tracking_number,
                        'shipping_tracking_url' => $op->shipping_tracking_url,

                        'shop' => [
                            'shop_id' => $op->product->shop->id ?? null,
                            'shop_name' => $op->product->shop->name ?? null,
                            'shop_image' => $op->product->shop->image_url ?? null
                        ]
                    ];
                });

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'buyer_id' => $order->buyer_id,
                'buyer_name' => $order->buyer->username ?? null,

                'total_price' => $order->price_total,
                'currency' => $order->price_currency,

                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,

                'trustap_transaction_id' => $order->trustap_transaction_id,
                'trustap_status' => $order->trustap_status,

                'created_at' => $order->created_at,

                'products' => $products
            ];
        });

        return response()->json([
            'type' => 'seller_orders',
            'orders' => $formattedOrders
        ]);
    }

    public function sellerOrderDetail($id)
    {
        $sellerId = auth()->id();

        $order = Order::whereHas('orderProducts', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        })
        ->with([
            'buyer',
            'orderProducts.product.shop',
            'orderProducts.product.mainImageRelation'
        ])
        ->findOrFail($id);

        $order->orderProducts = $order->orderProducts
            ->where('seller_id', $sellerId)
            ->values();

        $order->orderProducts->transform(function ($op) {

            $meta = json_decode($op->meta ?? '{}', true);

            $op->offer_id = $meta['offer_id'] ?? null;

            $op->product_name = $op->product->details()->first()->title ?? $op->product_title;

            $op->product_image = $op->product->main_image ?? null;

            $op->shop = $op->product->shop ?? null;

            return $op;
        });

        return response()->json([
            'type' => 'seller_order_detail',
            'order' => $order
        ]);
    }
    
}
