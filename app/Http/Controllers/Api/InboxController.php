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
use App\Models\Product;

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
        ])
            ->whereIn('id', $latestMessages)
            ->whereRaw("JSON_EXTRACT(meta, '$.type') = 'chat'")
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $messages
        ]);
    }

    /**
     * ✅ Get full chat messages with a specific user and optional product
     */
    // public function chatThread(Request $request)
    // {
    //     $user = $request->user();

    //     $data = $request->validate([
    //         'recipient_id' => 'required|integer|exists:users,id',
    //         'product_id' => 'nullable|integer|exists:products,id',
    //         'offer_id' => 'nullable|integer|exists:offers,id',
    //     ]);

    //     $query = OfferMessage::with(['sender:id,username,avatar', 'recipient:id,username,avatar'])
    //         ->where(function ($q) use ($user, $data) {
    //             $q->where(function ($sub) use ($user, $data) {
    //                 $sub->where('sender_id', $user->id)
    //                     ->where('recipient_id', $data['recipient_id']);
    //             })
    //                 ->orWhere(function ($sub) use ($user, $data) {
    //                     $sub->where('sender_id', $data['recipient_id'])
    //                         ->where('recipient_id', $user->id);
    //                 });
    //         });

    //     // 🟢 If offer_id given → show all messages linked to this offer
    //     if (!empty($data['offer_id'])) {
    //         $query->where('offer_id', $data['offer_id']);
    //     } 
    //     // 🟢 If product_id given → show all offers/messages for this product
    //     elseif (!empty($data['product_id'])) {
    //         $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.product_id')) = ?", [$data['product_id']]);
    //     }
    //     // 🟡 Otherwise → show only simple chat
    //     else {
    //         $query->whereNull('offer_id')
    //             ->whereRaw("JSON_EXTRACT(meta, '$.type') = 'chat'");
    //     }

    //     $messages = $query->orderBy('created_at')->get();

    //     return response()->json(['data' => $messages]);
    // }



    public function chatThread(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'recipient_id' => 'nullable|integer|exists:users,id',
            'product_id'   => 'nullable|integer|exists:products,id',
            'offer_id'     => 'nullable|integer|exists:offers,id',
        ]);

        // 🟢 Offer detail
        if (!empty($data['offer_id'])) {

            $offer = Offer::with([
                'product.shop',
                'buyer:id,username,avatar',
                'seller:id,username,avatar',
                'counters' => function ($q) {
                    $q->orderByDesc('id');
                }
            ])->find($data['offer_id']);

            if (!$offer) {
                return response()->json(['success' => false, 'message' => 'Offer not found'], 404);
            }

            // Latest counter (if any)
            $latestCounter = $offer->counters->first();

            $maxCounters = config('app.counter_limit');

            $buyerCounterCount = \App\Models\OfferCounter::where('offer_id', $offer->id)
                ->where('sender_id', $offer->buyer_id)
                ->where('type', 'counter_offer')
                ->count();

            $buyerCanCounter = $buyerCounterCount < $maxCounters;

            return response()->json([
                'success' => true,
                'type' => 'offer_detail',
                'data' => [

                    'offer_id'   => $offer->id,
                    'is_paid'    => $offer->is_paid, 
                    'status'     => $offer->status,
                    'type' => $latestCounter ? $latestCounter->type : 'offer',
                    'price'      => $latestCounter ? $latestCounter->price : $offer->price,
                    'message'    => $latestCounter ? $latestCounter->message : $offer->message,
                    'created_at' => $latestCounter ? $latestCounter->created_at : $offer->created_at,

                    'counter_limit' => $maxCounters,
                    'buyer_counter_count' => $buyerCounterCount,
                    'can_send_counter_offer' => $buyerCanCounter,

                    // Include full product
                    'product' => $offer->product,

                    // // Include full latest counter history
                    // 'counters_history' => $offer->counters->map(function ($c) {
                    //     return [
                    //         'id'         => $c->id,
                    //         'sender_id'  => $c->sender_id,
                    //         'recipient_id' => $c->recipient_id,
                    //         'price'      => $c->price,
                    //         'message'    => $c->message,
                    //         'type'       => $c->type,
                    //         'created_at' => $c->created_at,
                    //     ];
                    // }),

                    'buyer' => [
                        'id'       => $offer->buyer->id,
                        'username' => $offer->buyer->username,
                        'avatar'   => $offer->buyer->avatar,
                    ],

                    'seller' => [
                        'id'       => $offer->seller->id,
                        'username' => $offer->seller->username,
                        'avatar'   => $offer->seller->avatar,
                    ],
                ]
            ]);
        }


        // 🟢 Product-based: show latest offers from *all buyers* on my product
        if (!empty($data['product_id'])) {
            $product = Product::find($data['product_id']);
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            // Determine if user is the owner of the product
            $isOwner = $product->shop && $product->shop->user_id == $user->id;

            $offersQuery = Offer::where('product_id', $data['product_id'])
                ->with(['buyer:id,username,avatar', 'seller:id,username,avatar', 'counters' => fn($q) => $q->orderByDesc('id')])
                ->orderByDesc('id');

            if ($isOwner) {
                // User is product owner → show only offers sent to other shops
                $offersQuery->where('seller_id', $user->id)
                            ->where('is_owner_offer', true);
            } else {
                // User is not owner → show only offers received from product owner
                $offersQuery->where('buyer_id', $user->id)
                            ->where('is_owner_offer', true);
            }

            $offers = $offersQuery->get()
                ->groupBy($isOwner ? 'buyer_id' : 'seller_id')
                ->map(function ($groupedOffers) {
                    $offer = $groupedOffers->sortByDesc('id')->first();
                    $latestCounter = $offer->counters->sortByDesc('id')->first();

                    return [
                        'offer_id'      => $offer->id,
                        'is_paid'       => $offer->is_paid,
                        'status'        => $offer->status,
                        'type'          => $latestCounter ? 'counter_offer' : 'offer',
                        'price'         => $latestCounter ? $latestCounter->price : $offer->price,
                        'message'       => $latestCounter ? $latestCounter->message : $offer->message,
                        'created_at'    => $latestCounter ? $latestCounter->created_at : $offer->created_at,
                        'counter_limit' => config('app.counter_limit'),
                        'buyer_counter_count' => \App\Models\OfferCounter::where('offer_id', $offer->id)
                            ->where('sender_id', $offer->buyer_id)
                            ->where('type', 'counter_offer')
                            ->count(),
                        'can_send_counter_offer' => \App\Models\OfferCounter::where('offer_id', $offer->id)
                            ->where('sender_id', $offer->buyer_id)
                            ->where('type', 'counter_offer')
                            ->count() < config('app.counter_limit'),
                        'product' => $offer->product,
                        'buyer'   => [
                            'id' => $offer->buyer->id,
                            'username' => $offer->buyer->username,
                            'avatar' => $offer->buyer->avatar,
                        ],
                        'seller'  => [
                            'id' => $offer->seller->id,
                            'username' => $offer->seller->username,
                            'avatar' => $offer->seller->avatar,
                        ],
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'type' => 'product_offers',
                'data' => $offers
            ]);
        }



        // 🟡 Simple chat messages (non-offer)
        if (!empty($data['recipient_id'])) {
            $messages = OfferMessage::with(['sender:id,username,avatar', 'recipient:id,username,avatar'])
                ->where(function ($q) use ($user, $data) {
                    $q->where(function ($sub) use ($user, $data) {
                        $sub->where('sender_id', $user->id)
                            ->where('recipient_id', $data['recipient_id']);
                    })->orWhere(function ($sub) use ($user, $data) {
                        $sub->where('sender_id', $data['recipient_id'])
                            ->where('recipient_id', $user->id);
                    });
                })
                ->whereRaw("JSON_EXTRACT(meta, '$.type') = 'chat'")
                ->orderBy('created_at')
                ->get();

            return response()->json(['success' => true, 'type' => 'chat', 'data' => $messages]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid parameters.'], 422);
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
