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
        ]);

        $product = Product::with('shop.user')->findOrFail($data['product_id']);

        if (!$product->shop) {
            return response()->json(['message' => 'Product does not belong to a shop'], 422);
        }

        $sellerUserId = $product->shop->user_id;

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
            $recipient->notify(new OfferNotification([
                'title' => 'New Offer Received',
                'body' => $notificationText,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'offer_id' => $offer->id,
                'type' => 'offer',
                'product_id' => $product->id,
            ]));

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

    // my sent offers (buyer)
    public function sent(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('buyer_id', $user->id)
            ->with(['product','seller'])
            ->latest()
            ->paginate(20);

        return response()->json($offers);
    }

    // my received offers (seller)
    public function received(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('seller_id', $user->id)
            ->with(['product','buyer'])
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
            'status' => ['required', Rule::in(['accepted','rejected'])],
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
            $recipient->notify(new OfferNotification([
                'title' => 'Offer Response',
                'body' => $notificationText,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'offer_id' => $offer->id,
                'type' => 'offer_response',
                'status' => $data['status'],
            ]));

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

        if ($offer->seller_id !== $user->id) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'message' => 'nullable|string',
        ]);

        // ✅ Update offer price and set back to pending
        $offer->price = $data['price'];
        $offer->status = 'pending';
        $offer->save();

        // ✅ Create counter offer message
        $message = OfferMessage::create([
            'offer_id'     => $offer->id,
            'sender_id'    => $user->id,
            'recipient_id' => $offer->buyer_id,
            'body'         => "{$user->username} sent a counter offer for product \"{$offer->product->slug}\" at price {$data['price']}",
            'meta' => [
                'product_id'     => $offer->product->id,
                'product_title'  => $offer->product->slug,
                'price_offered'  => $data['price'],
                'type'           => 'counter_offer',
            ],
            'is_read' => false,
        ]);



           $recipient = User::find($offer->buyer_id);
        $notificationText = MessageTypeHelper::notificationText($message, $user->username);

        if ($recipient) {
            $recipient->notify(new OfferNotification([
                'title' => 'Counter Offer',
                'body' => $notificationText,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'offer_id' => $offer->id,
                'type' => 'counter_offer',
                'product_id' => $offer->product->id,
            ]));

            PusherHelper::trigger("private-notifications-{$recipient->id}", 'new-notification', [
                'title' => 'Counter Offer',
                'body' => $notificationText,
                'type' => 'counter_offer',
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
                'offer_id' => $offer->id,
            ]);

            PusherHelper::trigger("private-chat-{$recipient->id}", 'new-message', [
                'message' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar'),
            ]);
        }

        return response()->json([
            'message' => 'Counter offer sent successfully',
            'data' => $offer
        ]);
    }
}
