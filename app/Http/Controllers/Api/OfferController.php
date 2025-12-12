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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Helpers\FcmHelper;
class OfferController extends Controller
{
    // send an offer
    public function store(Request $request)
    {
        $user = $request->user();
        $userShop = $user->shop;

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

        
        if (!$userShop) {
            return response()->json([
                'message' => 'You cannot send an offer because you do not have a shop yet.'
            ], 403);
        }
        if ($requestedExpiry->greaterThan($maxExpiry)) {
            return response()->json([
                'message' => 'The offer expiry date cannot exceed 30 days from today.'
            ], 422);
        }

        $product = Product::with('shop.user')->findOrFail($data['product_id']);

        if (!$product->shop) {
            return response()->json(['message' => 'Product does not belong to a shop'], 422);
        }

        // 🔹 Prevent sending duplicate offers
        $existingOffer = Offer::where('product_id', $product->id)
            ->where('buyer_id', $user->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingOffer) {
            return response()->json([
                'message' => 'You already sent an offer for this product. Please wait until it is accepted or expired.'
            ], 422);
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
                    'is_owner_offer' => true,
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
                // ✅ Send notification
                $recipient = $recipientShop->user;
                // $notificationText = MessageTypeHelper::notificationText($message, $user->username);
                $notificationText = "{$user->username} sent you a new offer for product \"{$product->slug}\" at price {$data['price']} AED";
                if ($recipient) {
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
                            'shop' => [
                                'id' => $product->shop->id,
                                'name' => $product->shop->name,
                                'slug' => $product->shop->slug,
                                'image' => $product->shop->image_url,
                            ],
                        ],
                    ]);

                    PusherHelper::trigger("private-notifications.{$recipient->id}", 'new-notification', [
                        'title' => 'New Offer Received',
                        'body' => $notificationText,
                        'type' => 'offer',
                        'shop' => [
                            'id' => $product->shop->id,
                            'name' => $product->shop->name,
                            'slug' => $product->shop->slug,
                            'image' => $product->shop->image_url,
                        ],
                        'sender' => [
                            'id' => $user->id,
                            'username' => $user->username,
                            'avatar' => $user->avatar,
                        ],
                        'offer_id' => $offer->id,
                        'product_id' => $product->id,
                    ]);
                    
                    // FCM
                    if (!empty($recipient->fcm_token)) {
                        FcmHelper::send(
                            $recipient, 
                            'New Offer Received', 
                            $notificationText, 
                            [
                                'type' => 'offer',
                                'offer' => [
                                    'id' => $offer->id,
                                    'price' => $offer->price,
                                    'message' => $offer->message,
                                    'status' => $offer->status,
                                    'expires_at' => $offer->expires_at->toDateTimeString(),
                                ],
                                'product' => [
                                    'id' => $product->id,
                                    'slug' => $product->slug,
                                    'images' => $product->images->pluck('url')->toArray(),
                                    'price' => $product->price,
                                ],
                                'shop' => [
                                    'id' => $product->shop->id,
                                    'name' => $product->shop->name,
                                    'slug' => $product->shop->slug,
                                    'image' => $product->shop->image_url,
                                ],
                                'sender' => [
                                    'id' => $user->id,
                                    'username' => $user->username,
                                    'avatar' => $user->avatar,
                                ]
                            ],
                            $offer->id,      // $id
                            ''          // $type
                        );
                    }
                }
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
                $senderShop = $userShop; 

            // create offer
            $offer = Offer::create([
                'product_id' => $product->id,
                'buyer_id' => $user->id,
                'seller_id' => $sellerUserId,
                'price' => $data['price'],
                'message' => $data['message'] ?? null,
                'status' => 'pending',
                'expires_at' => $requestedExpiry,
                'is_owner_offer' => false,
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

            // ✅ Prepare notification
            $recipient = User::find($sellerUserId);
            // $notificationText = MessageTypeHelper::notificationText($message, $user->username);
            $notificationText = "{$user->username} sent you a new offer for product \"{$product->slug}\" at price {$data['price']} AED";

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
                        'shop' => [
                            'id' => $senderShop->id,
                            'name' => $senderShop->name,
                            'slug' => $senderShop->slug,
                            'image' => $senderShop->image_url,
                        ],
                    ],
                ]);

                // 🔔 Send via Pusher (notification + chat)
                PusherHelper::trigger("private-notifications.{$recipient->id}", 'new-notification', [
                    'title' => 'New Offer Received',
                    'body' => $notificationText,
                    'type' => 'offer',
                    'shop' => [
                        'id' => $senderShop->id,
                        'name' => $senderShop->name,
                        'slug' => $senderShop->slug,
                        'image' => $senderShop->image_url,
                    ],
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                    ],
                    'offer_id' => $offer->id,
                    'product_id' => $product->id,
                ]);

                
                // FCM
                if (!empty($recipient->fcm_token)) {
                    FcmHelper::send(
                        $recipient, 
                        'New Offer Received', 
                        $notificationText, 
                        [
                            'type' => 'offer',
                            'offer' => [
                                'id' => $offer->id,
                                'price' => $offer->price,
                                'message' => $offer->message,
                                'status' => $offer->status,
                                'expires_at' => $offer->expires_at->toDateTimeString(),
                            ],
                            'product' => [
                                'id' => $product->id,
                                'slug' => $product->slug,
                                'images' => $product->images->pluck('url')->toArray(),
                                'price' => $product->price,
                            ],
                            'shop' => [
                                'id' => $senderShop->id,
                                'name' => $senderShop->name,
                                'slug' => $senderShop->slug,
                                'image' => $senderShop->image_url,
                            ],
                            'sender' => [
                                'id' => $user->id,
                                'username' => $user->username,
                                'avatar' => $user->avatar,
                            ]
                        ],
                        $offer->id,      // $id
                        ''          // $type
                    );
                } 
            }
            return response()->json(['message' => 'Offer sent', 'data' => $offer], 201);
        }
    }

    public function received(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('seller_id', $user->id)
            ->where('is_owner_offer', false)
            // ->where(function ($q) {
            //     $q->whereNull('expires_at')
            //         ->orWhere('expires_at', '>', now());
            // })
            ->with(['product', 'buyer:id,username,avatar'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $productOffers) {
                $product = $productOffers->first()->product;

                // Group by buyer to get latest offer per buyer
                $latestByBuyer = $productOffers->groupBy('buyer_id')->map(function ($buyerOffers) {
                    return $buyerOffers->sortByDesc('id')->first();
                });

                return [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'product_image' => $product->main_image,
                    'offers_count' => $latestByBuyer->count(),
                    'latest_offers' => $latestByBuyer->values()->map(function ($offer) {
                        return [
                            'id'         => $offer->id,
                            'price'      => $offer->price,
                            'message'    => $offer->message,
                            'status'     => $offer->status,
                            'is_paid'    => $offer->is_paid, 
                            'created_at' => $offer->created_at,
                            'buyer'      => [
                                'id'       => $offer->buyer->id,
                                'username' => $offer->buyer->username,
                                'avatar'   => $offer->buyer->avatar,
                            ],
                        ];
                    }),
                ];
            })->values();

        // manual pagination
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $offset = ($page - 1) * $perPage;
        $paginated = new LengthAwarePaginator(
            $offers->slice($offset, $perPage)->values(),
            $offers->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json(['success' => true, 'data' => $paginated]);
    }


    public function receivedMultiple(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('buyer_id', $user->id)
            ->where('is_owner_offer', true)

            ->with(['product', 'seller:id,username,avatar'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $productOffers) {
                $product = $productOffers->first()->product;
                $latest  = $productOffers->sortByDesc('id')->first();

                return [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'product_image' => $product->main_image,
                    'offers_count' => $productOffers->count(),
                    'latest_offer' => [
                        'id'         => $latest->id,
                        'price'      => $latest->price,
                        'message'    => $latest->message,
                        'status'     => $latest->status,
                        'is_paid'    => $latest->is_paid, 
                        'created_at' => $latest->created_at,
                        'seller'      => [
                            'id'       => $latest->seller->id,
                            'username' => $latest->seller->username,
                            'avatar'   => $latest->seller->avatar,
                        ],
                    ],
                ];
            })->values();

        return $this->paginate($request, $offers);
    }



    public function sent(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('buyer_id', $user->id)
            // ->where(function ($q) {
            //     $q->whereNull('expires_at')
            //         ->orWhere('expires_at', '>', now());
            // })
            ->where('is_owner_offer', false)   
            ->with(['product', 'seller:id,username,avatar'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $productOffers) {
                $product = $productOffers->first()->product;
                $latest  = $productOffers->sortByDesc('id')->first();

                return [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'product_image' => $product->main_image,
                    'offers_count' => $productOffers->count(),
                    'latest_offer' => [
                        'id'         => $latest->id,
                        'price'      => $latest->price,
                        'message'    => $latest->message,
                        'status'     => $latest->status,
                        'is_paid'    => $latest->is_paid, 
                        'created_at' => $latest->created_at,
                        'seller'     => [
                            'id'       => $latest->seller->id,
                            'username' => $latest->seller->username,
                            'avatar'   => $latest->seller->avatar,
                        ],
                    ],
                ];
            })->values();

        // manual pagination
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $offset = ($page - 1) * $perPage;
        $paginated = new LengthAwarePaginator(
            $offers->slice($offset, $perPage)->values(),
            $offers->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json(['success' => true, 'data' => $paginated]);
    }


    public function sentMultiple(Request $request)
    {
        $user = $request->user();

        $offers = Offer::where('seller_id', $user->id)
            ->where('is_owner_offer', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with(['product', 'buyer:id,username,avatar'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $productOffers) {
                $product = $productOffers->first()->product;
                $latest  = $productOffers->sortByDesc('id')->first();

                return [
                    'product_id' => $product->id,
                    'product_slug' => $product->slug,
                    'product_image' => $product->main_image,
                    'offers_count' => $productOffers->count(),
                    'latest_offer' => [
                        'id'         => $latest->id,
                        'price'      => $latest->price,
                        'message'    => $latest->message,
                        'status'     => $latest->status,
                        'is_paid'    => $latest->is_paid, 
                        'created_at' => $latest->created_at,
                        'buyer'      => [
                            'id'       => $latest->buyer->id,
                            'username' => $latest->buyer->username,
                            'avatar'   => $latest->buyer->avatar,
                        ],
                    ],
                ];
            })->values();

        return $this->paginate($request, $offers);
    }



    // accept/reject offer
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $senderShop = $user->shop; 

        $offer = Offer::with('product.shop')->findOrFail($id);

        // only the seller (shop owner) can accept/reject their received offers
        // if ($offer->seller_id !== $user->id) {
        //     return response()->json(['message' => 'Not authorized to update this offer'], 403);
        // }

        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        // If already responded, optionally block repeated changes (decide policy)
        if ($offer->status !== 'pending') {
            return response()->json(['message' => 'Offer already responded to'], 422);
        }

        // ✅ Log response message

        $offer->status = $data['status'];
        $offer->responded_at = Carbon::now();
        $offer->save();

        // ✅ Send notification & pusher
        $recipient = User::find($offer->buyer_id);
        // $notificationText = MessageTypeHelper::notificationText($message, $user->username);
        $notificationText = "{$user->username} {$data['status']} your offer for \"{$offer->product->slug}\" at price {$offer->price} AED";

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
                    'product_id' => $offer->product_id,   
                    'shop' => [
                        'id' => $senderShop->id,
                        'name' => $senderShop->name,
                        'slug' => $senderShop->slug,
                        'image' => $senderShop->image_url,
                    ],
                ],
            ]);

            PusherHelper::trigger("private-notifications.{$recipient->id}", 'new-notification', [
                'title' => 'Offer Response',
                'body' => $notificationText,
                'type' => 'offer_response',
                'product_id' => $offer->product_id,   
                'status' => $data['status'],
                // 🔥 SHOP DETAILS
                'shop' => [
                    'id' => $senderShop->id,
                    'name' => $senderShop->name,
                    'slug' => $senderShop->slug,
                    'image' => $senderShop->image_url,
                ],
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]);
            
            // FCM
            if (!empty($recipient->fcm_token)) {
                FcmHelper::send(
                    $recipient, 
                    'Offer Response', 
                    $notificationText, 
                    [
                        'type' => 'offer_response',
                        'offer' => [
                            'id' => $offer->id,
                            'price' => $offer->price,
                            'message' => $offer->message,
                            'status' => $offer->status,
                            'expires_at' => $offer->expires_at->toDateTimeString(),
                        ],
                        'product' => [
                            'id' => $offer->product->id,
                            'slug' => $offer->product->slug,
                            'images' => $offer->product->images->pluck('url')->toArray(),
                            'price' => $offer->product->price,
                        ],

                        // 🔥 SHOP DETAILS
                        'shop' => [
                            'id' => $senderShop->id,
                            'name' => $senderShop->name,
                            'slug' => $senderShop->slug,
                            'image' => $senderShop->image_url,
                        ],
                        'sender' => [
                            'id' => $user->id,
                            'username' => $user->username,
                            'avatar' => $user->avatar,
                        ]
                    ],
                    $offer->id,          // $id
                    ''     // $type
                );
            } 
        }

        return response()->json(['message' => 'Offer updated', 'data' => $offer]);
    }

    // ✅ Seller sends counter-offer
    public function counterOffer(Request $request, $id)
    {
        $user = $request->user();
        $userShop = $user->shop;
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
        $offer->status = 'pending';
        $offer->responded_at = now();
        $offer->save();


        // ✅ Log in offer_counters table
        $recipientId = $user->id === $offer->seller_id ? $offer->buyer_id : $offer->seller_id;
        $recipient = User::find($recipientId);

        $counter = OfferCounter::create([
            'offer_id' => $offer->id,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'price' => $data['price'],
            'type' => 'counter_offer',
            'message' => $data['message'] ?? null,
        ]);

        $notificationText = "{$user->username} sent a counter offer at price {$data['price']} AED";

        $senderShop = $userShop;

        // ✅ Notify recipient

        if ($recipient) {
            Notification::create([
                'type' => 'counter_offer',
                'notifiable_type' => get_class($recipient),
                'notifiable_id' => $recipient->id,
                'data' => [
                    'title' => 'Counter Offer',
                    'body' => $notificationText,
                    'sender_id' => $user->id,
                    'recipient_id' => $recipient->id,
                    'offer_id' => $offer->id,
                    'product_id' => $offer->product->id,
                    'shop' => [
                        'id' => $senderShop->id,
                        'name' => $senderShop->name,
                        'slug' => $senderShop->slug,
                        'image' => $senderShop->image_url,
                    ],
                ],
            ]);

            PusherHelper::trigger("private-notifications.{$recipient->id}", 'new-notification', [
                'title' => 'Counter Offer',
                'body' => $notificationText,
                'type' => 'counter_offer',
                'product_id' => $offer->product->id, 
                'shop' => [
                    'id' => $senderShop->id,
                    'name' => $senderShop->name,
                    'slug' => $senderShop->slug,
                    'image' => $senderShop->image_url,
                ],
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]);
            // FCM
            if (!empty($recipient->fcm_token)) {
                FcmHelper::send(
                    $recipient, 
                    'Counter Offer', 
                    $notificationText, 
                    [
                        'type' => 'counter_offer',
                        'offer' => [
                            'id' => $offer->id,
                            'price' => $counter->price,
                            'message' => $counter->message,
                            'status' => $offer->status,
                            'expires_at' => $offer->expires_at->toDateTimeString(),
                        ],
                        'product' => [
                            'id' => $offer->product->id,
                            'slug' => $offer->product->slug,
                            'images' => $offer->product->images->pluck('url')->toArray(),
                            'price' => $offer->product->price,
                        ],
                        'shop' => [
                            'id' => $senderShop->id,
                            'name' => $senderShop->name,
                            'slug' => $senderShop->slug,
                            'image' => $senderShop->image_url,
                        ],
                        'sender' => [
                            'id' => $user->id,
                            'username' => $user->username,
                            'avatar' => $user->avatar,
                        ]
                    ],
                    $offer->id,          // $id
                    ''      // $type
                );
            } 
        }

        // Fetch the newly created counter offer
        $newCounter = OfferCounter::where('offer_id', $offer->id)
            ->where('sender_id', $user->id)
            ->latest()
            ->first();

        return response()->json([
            'message' => 'Counter offer sent successfully',
            'offer' => [
                'id' => $offer->id,
                'product_id' => $offer->product_id,
                'buyer_id' => $offer->buyer_id,
                'seller_id' => $offer->seller_id,

                // ❗ show updated counter price but NOT saving in DB
                'price' => $newCounter->price,

                // ❗ show type based on counter offer
                'type' => 'counter_offer',

                'message' => $newCounter->message,
                'status' => $offer->status,
                'expires_at' => $offer->expires_at,
                'responded_at' => $offer->responded_at,
                'created_at' => $offer->created_at,
                'updated_at' => $offer->updated_at,

                'product' => $offer->product,
            ],
        ]);
    }

    private function paginate(Request $request, Collection $items)
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $offset = ($page - 1) * $perPage;

        $paginated = new LengthAwarePaginator(
            $items->slice($offset, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json(['success' => true, 'data' => $paginated]);
    }
}
