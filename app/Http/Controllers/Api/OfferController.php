<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferMessage;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\User;
use App\Helpers\PusherHelper;
use App\Helpers\MessageTypeHelper;
use App\Notifications\OfferNotification;
use App\Models\Notification;
use App\Models\OfferCounter;
use App\Models\Shop;

class OfferController extends Controller
{
    // send an offer
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'price' => 'required|numeric|min:0',
            'message' => 'nullable|string',
            'expires_at' => 'required|date|after:today',
            'recipient_shop_ids' => 'nullable|array',
            'recipient_shop_ids.*' => 'integer|exists:shops,id',
        ]);

        $maxExpiry = now()->addDays(30);
        $requestedExpiry = Carbon::parse($data['expires_at']);

        if ($requestedExpiry->greaterThan($maxExpiry)) {
            return response()->json([
                'message' => 'The offer expiry date cannot exceed 30 days from today.'
            ], 422);
        }

        $product = Product::with('shop.user')->findOrFail($data['product_id']);

        if (!$product->shop) {
            return response()->json(['message' => 'Product does not belong to a shop'], 422);
        }

        $sellerUserId = $product->shop->user_id;

        // ✅ CASE 1: Seller sending to multiple shops
        if (!empty($data['recipient_shop_ids'])) {
            $recipientShops = Shop::with('user')
                ->whereIn('id', $data['recipient_shop_ids'])
                ->get();

            $createdOffers = [];

            foreach ($recipientShops as $recipientShop) {
                if ($recipientShop->user_id == $user->id) {
                    // Skip own shop
                    continue;
                }

                $offer = Offer::create([
                    'product_id' => $product->id,
                    'buyer_id' => $recipientShop->user_id,
                    'seller_id' => $user->id,
                    'price' => $data['price'],
                    'message' => $data['message'] ?? null,
                    'status' => 'pending',
                    'expires_at' => $requestedExpiry,
                ]);

                // ✅ Log to offer_counters
                OfferCounter::create([
                    'offer_id' => $offer->id,
                    'sender_id' => $user->id,
                    'recipient_id' => $recipientShop->user_id,
                    'price' => $data['price'],
                    'type' => 'offer',
                    'message' => $data['message'] ?? null,
                    'sent_at' => now(),
                ]);

                // ✅ Create message
                $message = OfferMessage::create([
                    'offer_id' => $offer->id,
                    'sender_id' => $user->id,
                    'recipient_id' => $recipientShop->user_id,
                    'body' => "{$user->username} sent an offer for product \"{$product->slug}\" at price {$data['price']} AED",
                    'meta' => [
                        'product_id' => $product->id,
                        'product_title' => $product->slug,
                        'price_offered' => $data['price'],
                        'type' => 'offer',
                    ],
                    'is_read' => false,
                ]);

                // ✅ Send notification
                $recipient = $recipientShop->user;
                $notificationText = MessageTypeHelper::notificationText($message, $user->username);

                Notification::create([
                    'type' => 'offer',
                    'notifiable_type' => get_class($recipient),
                    'notifiable_id' => $recipient->id,
                    'data' => [
                        'title' => 'New Offer Received',
                        'body' => $notificationText,
                        'sender_id' => $user->id,
                        'recipient_id' => $recipient->id,
                        'offer_id' => $offer->id,
                        'product_id' => $product->id,
                    ],
                ]);

                PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
                    'title' => 'New Offer Received',
                    'body' => $notificationText,
                    'type' => 'offer',
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                    ],
                    'offer_id' => $offer->id,
                    'product_id' => $product->id,
                ]);

                PusherHelper::trigger("private-chat-{$recipient->id}", 'new-message', [
                    'message' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar'),
                ]);

                $createdOffers[] = $offer;
            }

            return response()->json([
                'message' => 'Offer sent to selected shops successfully.',
                'data' => $createdOffers,
            ], 201);
        } else {

            if ($sellerUserId == $user->id) {
                return response()->json(['message' => 'You cannot send an offer to your own product'], 403);
            }

            // create offer
            $offer = Offer::create([
                'product_id' => $product->id,
                'buyer_id' => $user->id,
                'seller_id' => $sellerUserId,
                'price' => $data['price'],
                'message' => $data['message'] ?? null,
                'status' => 'pending',
                'expires_at' => $requestedExpiry,
            ]);
            // ✅ Log entry in offer_counters
            OfferCounter::create([
                'offer_id' => $offer->id,
                'sender_id' => $user->id,
                'recipient_id' => $sellerUserId,
                'price' => $data['price'],
                'type' => 'offer',
                'message' => $data['message'] ?? null,
                'sent_at' => now(),
            ]);

            // ✅ Create initial offer message
            $message = OfferMessage::create([
                'offer_id' => $offer->id,
                'sender_id' => $user->id,
                'recipient_id' => $sellerUserId,
                'body' => "{$user->username} sent an offer for the product \"{$product->slug}\" at price {$data['price']}",
                'meta' => [
                    'product_id' => $product->id,
                    'product_title' => $product->slug,
                    'price_offered' => $data['price'],
                    'type'           => 'offer',
                ],
                'is_read' => false,
            ]);



            // ✅ Prepare notification
            $recipient = User::find($sellerUserId);
            $notificationText = MessageTypeHelper::notificationText($message, $user->username);

            if ($recipient) {
                // ✅ Manual notification insert
                Notification::create([
                    'type' => 'offer',
                    'notifiable_type' => get_class($product->shop->user),
                    'notifiable_id' => $sellerUserId,
                    'data' => [
                        'title' => 'New Offer Received',
                        'body' => $notificationText,
                        'sender_id' => $user->id,
                        'recipient_id' => $sellerUserId,
                        'offer_id' => $offer->id,
                        'product_id' => $product->id,
                    ],
                ]);

                // 🔔 Send via Pusher (notification + chat)
                PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
                    'title' => 'New Offer Received',
                    'body' => $notificationText,
                    'type' => 'offer',
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                    ],
                    'offer_id' => $offer->id,
                    'product_id' => $product->id,
                ]);

                PusherHelper::trigger("private-chat-{$recipient->id}", 'new-message', [
                    'message' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar'),
                ]);
            }
            return response()->json(['message' => 'Offer sent', 'data' => $offer], 201);
        }
    }

    // my sent offers (buyer)
    public function sent(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('buyer_id', $user->id)
            ->with(['product', 'seller'])
            ->latest()
            ->paginate(20);

        return response()->json($offers);
    }

    // my received offers (seller)
    public function received(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('seller_id', $user->id)
            ->with(['product', 'buyer'])
            ->latest()
            ->paginate(20);

        return response()->json($offers);
    }

    // accept/reject offer
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $offer = Offer::with('product.shop')->findOrFail($id);

        // only the seller (shop owner) can accept/reject their received offers
        if ($offer->seller_id !== $user->id) {
            return response()->json(['message' => 'Not authorized to update this offer'], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        // If already responded, optionally block repeated changes (decide policy)
        if ($offer->status !== 'pending') {
            return response()->json(['message' => 'Offer already responded to'], 422);
        }

        // ✅ Log response message
        $message = OfferMessage::create([
            'offer_id'     => $offer->id,
            'sender_id'    => $user->id,
            'recipient_id' => $offer->buyer_id,
            'body'         => "{$user->username} {$data['status']} your offer for \"{$offer->product->slug}\" at price {$offer->price}",
            'meta' => [
                'product_id'     => $offer->product->id,
                'product_title'  => $offer->product->slug,
                'price_offered'  => $offer->price,
                'type'           => 'offer_response',
                'status'         => $data['status'],
            ],
            'is_read' => false,
        ]);

        $offer->status = $data['status'];
        $offer->responded_at = Carbon::now();
        $offer->save();



        // ✅ Send notification & pusher
        $recipient = User::find($offer->buyer_id);
        $notificationText = MessageTypeHelper::notificationText($message, $user->username);

        if ($recipient) {
            // ✅ Manual notification insert
            Notification::create([
                'type' => 'offer_response',
                'notifiable_type' => get_class($offer->buyer),
                'notifiable_id' => $offer->buyer_id,
                'data' => [
                    'title' => 'Offer Response',
                    'body' => $notificationText,
                    'sender_id' => $user->id,
                    'recipient_id' => $offer->buyer_id,
                    'offer_id' => $offer->id,
                    'status' => $data['status'],
                ],
            ]);

            PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
                'title' => 'Offer Response',
                'body' => $notificationText,
                'type' => 'offer_response',
                'status' => $data['status'],
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]);

            PusherHelper::trigger("private-chat-{$recipient->id}", 'new-message', [
                'message' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar'),
            ]);
        }

        return response()->json(['message' => 'Offer updated', 'data' => $offer]);
    }

    // ✅ Seller sends counter-offer
    public function counterOffer(Request $request, $id)
    {
        $user = $request->user();
        $offer = Offer::with('product.shop')->findOrFail($id);

        if ($offer->seller_id !== $user->id && $offer->buyer_id !== $user->id) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'message' => 'nullable|string',
        ]);

        // ✅ Dynamic limit for buyer counter offers
        $maxCounters = config('app.counter_limit');

        // ✅ Count buyer's counter offers on this offer
        $buyerCounterCount = \App\Models\OfferCounter::where('offer_id', $offer->id)
            ->where('sender_id', $offer->buyer_id)
            ->where('type', 'counter_offer')
            ->count();

        if ($user->id === $offer->buyer_id && $buyerCounterCount >= $maxCounters) {
            return response()->json(['message' => "You have reached the maximum of {$maxCounters} counter offers allowed."], 403);
        }

        // ✅ Update offer
        $offer->price = $data['price'];
        $offer->status = 'pending';
        $offer->save();

        // ✅ Log in offer_counters table
        \App\Models\OfferCounter::create([
            'offer_id' => $offer->id,
            'sender_id' => $user->id,
            'recipient_id' => $user->id === $offer->seller_id ? $offer->buyer_id : $offer->seller_id,
            'price' => $data['price'],
            'type' => 'counter_offer',
            'message' => $data['message'] ?? null,
        ]);

        // ✅ Create chat message
        $message = OfferMessage::create([
            'offer_id'     => $offer->id,
            'sender_id'    => $user->id,
            'recipient_id' => $user->id === $offer->seller_id ? $offer->buyer_id : $offer->seller_id,
            'body'         => "{$user->username} sent a counter offer for product \"{$offer->product->slug}\" at price {$data['price']}",
            'meta' => [
                'product_id'     => $offer->product->id,
                'product_title'  => $offer->product->slug,
                'price_offered'  => $data['price'],
                'type'           => 'counter_offer',
            ],
            'is_read' => false,
        ]);

        // ✅ Notify recipient
        $recipient = User::find($message->recipient_id);
        if ($recipient) {
            Notification::create([
                'type' => 'counter_offer',
                'notifiable_type' => get_class($recipient),
                'notifiable_id' => $recipient->id,
                'data' => [
                    'title' => 'Counter Offer',
                    'body' => "{$user->username} sent a counter offer at price {$data['price']}",
                    'sender_id' => $user->id,
                    'recipient_id' => $recipient->id,
                    'offer_id' => $offer->id,
                    'product_id' => $offer->product->id,
                ],
            ]);

            PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
                'title' => 'Counter Offer',
                'body' => "{$user->username} sent a counter offer at price {$data['price']}",
                'type' => 'counter_offer',
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Counter offer sent successfully',
            'data' => $offer,
        ]);
    }

    // public function counterOffer(Request $request, $id)
    // {
    //     $user = $request->user();
    //     $offer = Offer::with('product.shop')->findOrFail($id);

    //     if ($offer->seller_id !== $user->id) {
    //         return response()->json(['message' => 'Not authorized'], 403);
    //     }

    //     $data = $request->validate([
    //         'price' => 'required|numeric|min:0',
    //         'message' => 'nullable|string',
    //     ]);

    //     // ✅ Update offer price and set back to pending
    //     $offer->price = $data['price'];
    //     $offer->status = 'pending';
    //     $offer->save();

    //     // ✅ Create counter offer message
    //     $message = OfferMessage::create([
    //         'offer_id'     => $offer->id,
    //         'sender_id'    => $user->id,
    //         'recipient_id' => $offer->buyer_id,
    //         'body'         => "{$user->username} sent a counter offer for product \"{$offer->product->slug}\" at price {$data['price']}",
    //         'meta' => [
    //             'product_id'     => $offer->product->id,
    //             'product_title'  => $offer->product->slug,
    //             'price_offered'  => $data['price'],
    //             'type'           => 'counter_offer',
    //         ],
    //         'is_read' => false,
    //     ]);



    //     $recipient = User::find($offer->buyer_id);
    //     $notificationText = MessageTypeHelper::notificationText($message, $user->username);

    //     if ($recipient) {
    //         // ✅ Manual notification insert
    //         Notification::create([
    //             'type' => 'counter_offer',
    //             'notifiable_type' => get_class($offer->buyer),
    //             'notifiable_id' => $offer->buyer_id,
    //             'data' => [
    //                 'title' => 'Counter Offer',
    //                 'body' => $notificationText,
    //                 'sender_id' => $user->id,
    //                 'recipient_id' => $offer->buyer_id,
    //                 'offer_id' => $offer->id,
    //                 'product_id' => $offer->product->id,
    //             ],
    //         ]);

    //         PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
    //             'title' => 'Counter Offer',
    //             'body' => $notificationText,
    //             'type' => 'counter_offer',
    //             'sender' => [
    //                 'id' => $user->id,
    //                 'username' => $user->username,
    //                 'avatar' => $user->avatar,
    //             ],
    //             'offer_id' => $offer->id,
    //         ]);

    //         PusherHelper::trigger("private-chat-{$recipient->id}", 'new-message', [
    //             'message' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar'),
    //         ]);
    //     }

    //     return response()->json([
    //         'message' => 'Counter offer sent successfully',
    //         'data' => $offer
    //     ]);
    // }

}
