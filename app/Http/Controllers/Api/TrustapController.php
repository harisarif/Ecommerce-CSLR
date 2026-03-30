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

class TrustapController extends Controller
{
    protected $trustap;

    public function __construct(TrustapService $trustap)
    {
        $this->trustap = $trustap;
    }

    public function trustapTransactions(Request $request)
    {
        $user = $request->user();
        $shop = \App\Models\Shop::where('user_id', $user->id)->firstOrFail();

        $data = \App\Models\PaymentTransfer::with(['order.orderProducts', 'order.buyer', 'shop'])
            ->whereNotNull('trustap_transaction_id')
            ->where(function ($q) use ($shop, $user) {
                $q->where('shop_id', $shop->id) // received
                ->orWhereHas('order', function ($q2) use ($user) {
                    $q2->where('buyer_id', $user->id); // sent
                });
            })
            ->get()
            ->map(function ($pt) use ($user) {

                $order = $pt->order;
                $isSender = optional($order)->buyer_id === $user->id;

                $product = optional($order?->orderProducts->first());

                $productTitle = $product?->product_title ?? 'Product';

                $buyer = optional($order)->buyer;
                $buyerName = $buyer?->name
                    ?? trim(($buyer?->first_name ?? '') . ' ' . ($buyer?->last_name ?? ''))
                    ?? 'Buyer';

                $shopName = optional($pt->shop)->name ?? 'Shop';

                return [
                    'source' => 'trustap',
                    'id' => $pt->trustap_transaction_id,

                    'message' => $isSender
                        ? "Payment sent to {$shopName} for {$productTitle}"
                        : "Payment received from {$buyerName} for {$productTitle}",

                    'type' => $isSender ? 'debit' : 'credit',

                    'amount' => ($pt->amount_cents ?? $pt->checkout_amount_cents ?? 0) / 100,
                    'currency' => strtoupper($pt->currency ?? 'AED'),

                    'status' => match ($pt->status) {
                        'on_hold' => 'Escrow (On Hold)',
                        'released' => 'Completed',
                        default => $pt->status,
                    },

                    'created_at' => $pt->created_at?->toDateTimeString(),

                    'release_at' => $pt->status === 'on_hold'
                        ? optional($pt->release_at)->toDateTimeString()
                        : null,
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json($data);
    }


    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            $token = $this->trustap->refreshAccessToken($user);

            return response()->json([
                'success' => true,
                'access_token' => $token
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage() // optional (remove in production)
            ], 400);
        }
    }




};