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
use App\Models\Shop;
use App\Helpers\FcmHelper;
use GuzzleHttp\Client;


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
            ->get()
            ->map(function ($msg) {
                // If NO offer exists but product_id is inside meta, attach product manually
                if (!$msg->offer_id && isset($msg->meta['product_id'])) {
                    $msg->product = Product::select('id', 'slug')
                        ->find($msg->meta['product_id']);
                } else {
                    // When offer exists use offer.product
                    $msg->product = $msg->offer->product ?? null;
                }
                return $msg;
            });

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
            'recipient_id' => 'nullable|integer|exists:users,id',
            'product_id'   => 'nullable|integer|exists:products,id',
            'offer_id'     => 'nullable|integer|exists:offers,id',
            'source'       => 'nullable|string|in:receive,sent_multiple', 
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
            
            // Build full history timeline
            $history = collect();

            // 1️⃣ Add original offer
            $history->push([
                'type'        => 'offer',
                'sender_id'   => $offer->buyer_id,
                'recipient_id'=> $offer->seller_id,
                'price'       => $offer->price,
                'message'     => $offer->message,
                'status'      => $offer->status,
                'created_at'  => $offer->created_at,
            ]);

            // 2️⃣ Add all counters (but sorted oldest → newest)
            foreach ($offer->counters()->orderBy('id')->get() as $c) {
                $history->push([
                    'type'        => $c->type,   // counter_offer | counter_accept | counter_reject
                    'sender_id'   => $c->sender_id,
                    'recipient_id'=> $c->recipient_id,
                    'price'       => $c->price,
                    'message'     => $c->message,
                    'created_at'  => $c->created_at,
                ]);
            }

            // 3️⃣ Add final status update (only if accepted/rejected/paid)
            if (in_array($offer->status, ['accepted', 'rejected', 'paid'])) {
                $history->push([
                    'type'        => 'status_update',
                    'status'      => $offer->status,
                    'created_at'  => $offer->updated_at,
                ]);
            }

            // 4️⃣ Sort entire history by datetime (ASCENDING)
            $history = $history->sortBy('created_at')->values();
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
                    'history' => $history
                ]
            ]);
        }


        if (!empty($data['product_id']) && empty($data['recipient_id'])) {

            $product = Product::with('shop')->find($data['product_id']);
            if (!$product) {
                return response()->json(['success' => false, 'message' => 'Product not found'], 404);
            }

            $isOwner = $product->shop && $product->shop->user_id == $user->id;

            $source = $request->input('source'); // sent_multiple / receive

            $offersQuery = Offer::where('product_id', $product->id)
                ->with(['buyer:id,username,avatar', 'seller:id,username,avatar', 'counters' => fn($q) => $q->orderByDesc('id')])
                ->orderByDesc('id');

            if ($isOwner && $source === 'sent_multiple') {
                // I am owner → show only offers I sent to other shops
                $offersQuery->where('seller_id', $user->id)
                    ->where('is_owner_offer', true);
                $groupBy = 'buyer_id';
            } elseif ($isOwner && $source === 'receive') {
                // I am owner → show only offers I received from other shops
                $offersQuery->where('seller_id', $user->id)
                    ->where('is_owner_offer', false);
                $groupBy = 'buyer_id';
            } elseif (!$isOwner) {
                // not owner → normal receive (offers sent by owner to me)
                $offersQuery->where('buyer_id', $user->id)
                    ->where('is_owner_offer', true);
                $groupBy = 'seller_id';
            }

            $offers = $offersQuery->get()
                ->groupBy($groupBy)
                ->map(function ($groupedOffers) use ($isOwner) {

                    $offer = $groupedOffers->sortByDesc('id')->first();
                    $latestCounter = $offer->counters->sortByDesc('id')->first();

                    // buyer shop info
                    $buyerShop = Shop::where('user_id', $offer->buyer_id)
                        ->select('id', 'name', 'image')
                        ->first();

                    return [
                        'offer_id'      => $offer->id,
                        'is_paid'       => $offer->is_paid,
                        'status'        => $offer->status,
                        'type'          => $latestCounter ? $latestCounter->type : 'offer',
                        'price'         => $latestCounter ? $latestCounter->price : $offer->price,
                        'message'       => $latestCounter ? $latestCounter->message : $offer->message,
                        'created_at'    => $latestCounter ? $latestCounter->created_at : $offer->created_at,

                        'counter_limit' => config('app.counter_limit'),
                        'buyer_counter_count' => \App\Models\OfferCounter::where('offer_id', $offer->id)
                            ->where('sender_id', $offer->buyer_id)
                            ->where('type', 'counter_offer')
                            ->count(),

                        'can_send_counter_offer' =>
                        \App\Models\OfferCounter::where('offer_id', $offer->id)
                            ->where('sender_id', $offer->buyer_id)
                            ->where('type', 'counter_offer')
                            ->count() < config('app.counter_limit'),

                        'product' => $offer->product,

                        'buyer' => [
                            'id'       => $offer->buyer->id,
                            'username' => $offer->buyer->username,
                            'avatar'   => $offer->buyer->avatar,
                        ],

                        'buyer_shop' => $isOwner && $buyerShop ? [
                            'id'   => $buyerShop->id,
                            'name' => $buyerShop->name,
                            'logo' => $buyerShop->image,
                        ] : null,

                        'seller'  => [
                            'id'       => $offer->seller->id,
                            'username' => $offer->seller->username,
                            'avatar'   => $offer->seller->avatar,
                        ],
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'type'    => 'product_offers',
                'data'    => $offers
            ]);
        }



        // 🟡 Simple chat messages (non-offer)
        if (!empty($data['recipient_id']) && !empty($data['product_id'])) {

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
                ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.product_id')) AS UNSIGNED) = ?", [$data['product_id']])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.type')) = 'chat'")
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'type'    => 'chat',
                'data'    => $messages
            ]);
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
            'product_id' => 'required|integer|exists:products,id',
            'offer_id' => 'nullable|integer|exists:offers,id',
        ]);


        if ($data['recipient_id'] == $user->id) {
            return response()->json([
                'message' => 'You cannot send a message to yourself.'
            ], 422);
        }
        $text = trim(preg_replace('/\s+/', ' ', $data['body']));


        // This is used for gemini detect violation in chat
        try {
            $client = new \GuzzleHttp\Client();
            $apiKey = env('GEMINI_API_KEY');

            $response = $client->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
                [
                    'headers' => [
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => "Check the following message carefully. 
                                        Does it contain any personal data like an email, phone number, social media link, account URL, 
                                        or any instruction to share personal info?

                                        Only answer: YES or NO.

                                        Message:
                                        \"{$text}\"
                                        "
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            );

            $body = json_decode($response->getBody(), true);

            // 🔹 Safe read (no crash)
            $answer = strtolower(
                $body['candidates'][0]['content']['parts'][0]['text'] ?? ''
            );

            if (str_contains($answer, 'yes')) {
                return response()->json([
                    'message' => 'Your message violates policy: personal data not allowed.'
                ], 422);
            }

        } catch (\Exception $e) {

            \Log::warning('Gemini API failed', [
                'error' => $e->getMessage(),
            ]);

            // 🔹 Fallback manual check (never crashes)
            if ($this->manualCheckMessage($text)) {
                return response()->json([
                    'message' => 'Your message contains restricted personal data.'
                ], 422);
            }
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
        $chatChannel = "private-chat.{$recipient->id}";
        PusherHelper::trigger($chatChannel, 'new-message', [
            'message' => $message,
        ]);

        // ✅ 2️⃣ Pusher event for notifications (separate)
        $notifChannel = "private-notifications.{$recipient->id}";
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

        // ✅ 3️⃣ Send FCM Push Notification
        if (!empty($recipient->fcm_token)) {

            FcmHelper::send(
                $recipient,          // $user
                'New Message',        // $title
                $notificationText,    // $body
                [                     // $extraData
                    'type' => 'chat',
                    'product_id' => $meta['product_id'],
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                    ]
                ],
                $message->id          // $id
                // $type will default to 'notification'
            );
        }

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => $message->load('sender:id,username,avatar', 'recipient:id,username,avatar')
        ], 201);
    }

    private function manualCheckMessage(string $text): bool
    {
        $text = strtolower(trim($text));

        // 1️⃣ Regex for emails, phone numbers, URLs
        $patterns = [
            '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/',          // emails
            '/\+?\d[\d\s\-]{7,}\d/',                             // phone numbers
            '/https?:\/\/[^\s]+|www\.[^\s]+/',                  // URLs
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true; // blocked
            }
        }

        // 2️⃣ Keyword-based detection for instructions to share info
        $keywords = [
            'i will send you my email',
            'my phone is',
            'contact me on',
            'dm me',
            'whatsapp',
            'telegram',
            'instagram',
            'facebook',
            'snapchat',
            'linkedin',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true; // blocked
            }
        }

        return false; // safe
    }
}
