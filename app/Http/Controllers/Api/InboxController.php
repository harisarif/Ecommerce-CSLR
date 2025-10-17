<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\PusherHelper;
use App\Helpers\MessageTypeHelper;
use App\Models\User;
use App\Notifications\OfferNotification;
use App\Models\Notification;

class InboxController extends Controller
{
    // list all offers where I'm buyer or seller
    // public function index(Request $request)
    // {
    //     $user = $request->user();

    //     $offers = Offer::with(['product', 'buyer', 'seller'])
    //         ->where(function ($q) use ($user) {
    //             $q->where('buyer_id', $user->id)
    //                 ->orWhere('seller_id', $user->id);
    //         })
    //         ->orderByDesc('updated_at')
    //         ->paginate(30);

    //     return response()->json($offers);
    // }

    public function index(Request $request)
    {
        $user = $request->user();

        // Step 1: Get IDs of latest messages per conversation
        $latestMessages = OfferMessage::select(DB::raw('MAX(id) as id'))
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->groupBy(
                DB::raw('LEAST(sender_id, recipient_id)'), // Ensure same pair groups both directions
                DB::raw('GREATEST(sender_id, recipient_id)'),
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.product_id')), 'no_product')")
            )
            ->pluck('id');

        // Step 2: Fetch those latest messages
        $messages = OfferMessage::with([
            'sender:id,username,avatar',
            'recipient:id,username,avatar',
            'offer.product:id,slug',
            'offer.product.images'
        ])
            ->whereIn('id', $latestMessages)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $messages
        ]);
    }

    /**
     * ✅ Get full chat messages with a specific user and optional product
     */
    public function chatThread(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'offer_id' => 'nullable|integer|exists:offers,id',
        ]);

        $query = OfferMessage::with(['sender:id,username,avatar', 'recipient:id,username,avatar'])
            ->where(function ($q) use ($user, $data) {
                $q->where(function ($sub) use ($user, $data) {
                    $sub->where('sender_id', $user->id)
                        ->where('recipient_id', $data['recipient_id']);
                })
                    ->orWhere(function ($sub) use ($user, $data) {
                        $sub->where('sender_id', $data['recipient_id'])
                            ->where('recipient_id', $user->id);
                    });
            });

        // 🟢 If offer_id is given → show offer-related messages only
        if (!empty($data['offer_id'])) {
            $query->where('offer_id', $data['offer_id']);
        } else {
            // 🟡 Otherwise, show only pure chat (no offer)
            $query->whereNull('offer_id')
                ->whereRaw("JSON_EXTRACT(meta, '$.type') = 'chat'");
        }

        // Optional product filter
        if (!empty($data['product_id'])) {
            $query->whereRaw("JSON_EXTRACT(meta, '$.product_id') = ?", [$data['product_id']]);
        }

        $messages = $query->orderBy('created_at')->get();

        return response()->json(['data' => $messages]);
    }


    /**
     * ✅ Send message (either simple chat or offer chat)
     */
    public function sendMessage(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'body' => 'required|string',
            'product_id' => 'nullable|integer|exists:products,id',
            'offer_id' => 'nullable|integer|exists:offers,id',
        ]);


        if ($data['recipient_id'] == $user->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself.'
            ], 422);
        }

        $meta = [
            'type' => !empty($data['offer_id']) ? 'offer_chat' : 'chat',
            'product_id' => $data['product_id'] ?? null,
        ];

        $message = OfferMessage::create([
            'offer_id' => $data['offer_id'] ?? null,
            'sender_id' => $user->id,
            'recipient_id' => $data['recipient_id'],
            'body' => $data['body'],
            'meta' => $meta,
            'is_read' => false,
        ]);


        // ✅ Load sender & recipient for clean data
        $message->load('sender:id,username,avatar', 'recipient:id,username,avatar');

        // ✅ Generate notification text
        $notificationText = MessageTypeHelper::notificationText($message, $user->username);

        $recipient = User::find($data['recipient_id']);

        // ✅ Save to database (Laravel Notification)
        if ($recipient) {
            Notification::create([
                'type' => $meta['type'],
                'notifiable_type' => get_class($recipient),
                'notifiable_id' => $recipient->id,
                'data' => [
                    'title' => 'New Message',
                    'body' => $notificationText,
                    'sender_id' => $user->id,
                    'recipient_id' => $recipient->id,
                    'message_id' => $message->id,
                    'product_id' => $meta['product_id'] ?? null,
                    'type' => $meta['type'],
                    'created_at' => now()->toDateTimeString(),
                ],
            ]);
        }

        // ✅ 1️⃣ Pusher event for chat messages
        $chatChannel = "private-chat-{$recipient->id}";
        PusherHelper::trigger($chatChannel, 'new-message', [
            'message' => $message,
        ]);

        // ✅ 2️⃣ Pusher event for notifications (separate)
        $notifChannel = "private-notifications-{$recipient->id}";
        PusherHelper::trigger($notifChannel, 'new-notification', [
            'title' => 'New Message',
            'body' => $notificationText,
            'sender' => [
                'id' => $user->id,
                'username' => $user->username,
                'avatar' => $user->avatar,
            ],
            'type' => $meta['type'],
            'message_id' => $message->id,
        ]);


        return response()->json([
            'message' => 'Message sent successfully',
            'data' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar')
        ], 201);
    }
}
