<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopReview;
use App\Models\ShopFollower;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Helpers\PusherHelper;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductInterested;
use App\Models\Wishlist;
use App\Notifications\ShopFollowNotification;
// use Illuminate\Support\Facades\Notification;
use App\Helpers\FcmHelper;
use App\Models\Review;

class ShopController extends Controller
{
    // create or update shop for logged-in user
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable','string','max:255', Rule::unique('shops','slug')->ignore(optional($user->shop)->id)],
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:512',
            'settings' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', // ✅ new rule
        ]);

        // handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // make sure folder exists
            $destination = public_path('images/shops');
            if (!file_exists($destination)) {
                mkdir($destination, 0777, true);
            }

            $filename = Str::slug($data['name'] ?? $user->username) . '-' . time() . '.' . $file->getClientOriginalExtension();

            // move file
            $file->move($destination, $filename);

            // save relative path into DB
            $data['image'] = 'images/shops/' . $filename;
        }


        $shop = Shop::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['user_id' => $user->id])
        );

        return response()->json([
            'message' => 'Shop saved',
            'data' => $shop->fresh()
        ], 201);
    }

    // get my shop (logged in)
    public function myShop(Request $request)
    {
        $user = $request->user();

        $shop = $user->shop()
            ->with('products')
            ->withCount(['followers', 'reviews'])   // ✅ include counts
            ->withAvg('reviews', 'rating')
            ->first();

        if (!$shop) {
            return response()->json(['message' => 'No shop found for this user'], 404);
        }

        $ratingStats = Review::whereHas('product', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
        ->first();

        $averageRating = $ratingStats->avg_rating
            ? round($ratingStats->avg_rating, 1)
            : 0;

        $reviewsCount = $ratingStats->total_reviews ?? 0;
        $followingCount = $user->followedShops()->count();

        return response()->json([
            'data' => $shop,
            'followers_count' => $shop->followers_count,
            'following_count' => $followingCount,
            'average_rating' => $shop->reviews_avg_rating ? round($shop->reviews_avg_rating, 1) : null,
            'reviews_count' => $shop->reviews_count,
            'shop_rating' => [
                'average' => $averageRating,
                'total_reviews' => $reviewsCount,
            ],

        ]);
    }


    // public shop page by id (or slug)
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $shop = is_numeric($id)
            ? Shop::with('products', 'user.followedShops')
                ->withCount(['followers', 'reviews']) // ✅ include reviews_count directly
                ->withAvg('reviews', 'rating')
                ->find($id)
            : Shop::with('products', 'user.followedShops')
                ->withCount(['followers', 'reviews'])
                ->withAvg('reviews', 'rating')
                ->where('slug', $id)
                ->first();

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        
       // ⭐ PRODUCT-BASED SHOP RATING (Scenario 2)
        $ratingStats = Review::whereHas('product', function ($q) use ($shop) {
            $q->where('shop_id', $shop->id);
        })
        ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
        ->first();

        $averageRating = $ratingStats->avg_rating
            ? round($ratingStats->avg_rating, 1)
            : 0;

        $reviewsCount = $ratingStats->total_reviews ?? 0;

        // 🔹 Sold products (paid orders only)
        $soldProducts = $shop->soldOrderProducts->map(function ($op) {
            return [
                'product_id' => $op->product_id,
                'title' => $op->product_title,
                'price' => $op->product_unit_price,
                'quantity' => $op->product_quantity,
                'total_price' => $op->product_total_price,
                'currency' => $op->product_currency,
                'sold_at' => $op->created_at,
            ];
        });

        return response()->json([
            'data' => $shop,
            // ✅ FOLLOW INFO
            'is_followed' => $shop->isFollowedBy($user),
            'followers_count' => $shop->followers_count,
            'following_count' => $shop->followingCount(),
            'average_rating' => $shop->reviews_avg_rating ? round($shop->reviews_avg_rating, 1) : null,
            'reviews_count' => $shop->reviews_count, // ✅ directly from withCount

            // ✅ NEW
            'sold_products_count' => $soldProducts->count(),
            // 'sold_products' => $soldProducts,
              // ✅ RATING FROM PRODUCT REVIEWS
            'shop_rating' => [
                'average' => $averageRating,
                'total_reviews' => $reviewsCount,
            ],
        ]);
    }





    // ✅ Add Review
    public function addReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $shop = Shop::findOrFail($id);

        $review = ShopReview::updateOrCreate(
            ['shop_id' => $shop->id, 'user_id' => $request->user()->id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        return response()->json(['message' => 'Review saved', 'data' => $review]);
    }

    // ✅ Get Reviews
    public function getReviews($id)
    {
        $shop = Shop::findOrFail($id);
        $reviews = $shop->reviews()->with('user')->latest()->get();

        return response()->json(['data' => $reviews]);
    }

    // ✅ Follow Shop
    public function follow(Request $request, $id)
    {
        $user = $request->user();

        $shop = Shop::findOrFail($id);
        
        // ❌ Prevent following own shop
        if ($shop->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot follow your own shop'
            ], 403);
        }

        $shop->followers()->syncWithoutDetaching([$request->user()->id]);
        
        $recipient = $shop->user;
         // ✅ Create notification record manually
        $notificationText = "{$user->username} followed your shop \"{$shop->name}\"";

        // ✅ Save notification in DB
        Notification::create([
            'type' => 'shop_follow',
            'notifiable_type' => get_class($recipient),
            'notifiable_id' => $recipient->id,
            'data' => [
                'type' => 'shop_follow',
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'follower_id' => $user->id,
                'follower_username' => $user->username,
                'message' => $notificationText,
            ],
        ]);


        // ✅ Send realtime push manually via your existing helper
        PusherHelper::trigger(
            "private-notifications.{$recipient->id}",
            'shop-follow',
            [
                'type' => 'shop_follow',
                'title' => 'New Follower',
                'body' => $notificationText,
                'shop' => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'image' => $shop->image_url,
                ],
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]
        );

        // ✅ FCM push
        if (!empty($recipient->fcm_token)) {
            FcmHelper::send(
                $recipient,
                'New Follower',
                $notificationText,
                [
                    'type' => 'shop_follow',
                    'shop' => [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'slug' => $shop->slug,
                        'image' => $shop->image_url,
                    ],
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                    ],
                ],
                $shop->id,
                'shop_follow'
            );
        }


        return response()->json(['message' => 'Followed shop']);
    }

    // ✅ Unfollow Shop
    public function unfollow(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);

        $shop->followers()->detach($request->user()->id);

        $user = $request->user();
            // ❌ Prevent unfollowing own shop
        if ($shop->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot unfollow your own shop'
            ], 403);
        }

        
        $recipient = $shop->user;

        $notificationText = "{$user->username} unfollowed your shop \"{$shop->name}\"";

        // ✅ DB notification
        Notification::create([
            'type' => 'shop_unfollow',
            'notifiable_type' => get_class($recipient),
            'notifiable_id' => $recipient->id,
            'data' => [
                'type' => 'shop_unfollow',
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'follower_id' => $user->id,
                'follower_username' => $user->username,
                'message' => $notificationText,
            ],
        ]);

        // ✅ Send realtime push manually via your existing helper
        PusherHelper::trigger(
            "private-notifications.{$recipient->id}",
            'shop-unfollow',
            [
                'type' => 'shop_unfollow',
                'title' => 'Shop Unfollowed',
                'body' => $notificationText,
                'shop' => [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'image' => $shop->image_url,
                ],
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar' => $user->avatar,
                ],
            ]
        );

        // ✅ FCM
        if (!empty($recipient->fcm_token)) {
            FcmHelper::send(
                $recipient,
                'Shop Unfollowed',
                $notificationText,
                [
                    'type' => 'shop_unfollow',
                    'shop' => [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'slug' => $shop->slug,
                        'image' => $shop->image_url,
                    ],
                    'sender' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'avatar' => $user->avatar,
                    ],
                ],
                $shop->id,
                'shop_unfollow'
            );
        }


        return response()->json(['message' => 'Unfollowed shop']);
    }

    // ✅ Check if Following
    public function isFollowing(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $isFollowing = $shop->followers()->where('user_id', $request->user()->id)->exists();

        return response()->json(['following' => $isFollowing]);
    }


    public function shopsList(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id'
        ]);

        $productId = $data['product_id'];

        // Ensure the product belongs to the current user
        $product = Product::where('id', $productId)
                        ->where('user_id', $user->id)
                        ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or not yours.'
            ], 404);
        }

        // Shops that viewed this product
        $interestedShops = ProductInterested::where('product_id', $productId)
            ->pluck('viewer_shop_id')
            ->toArray();

        // Shops that added this product to wishlist
        $wishlistShops = Wishlist::where('product_id', $productId)
            ->join('shops', 'wishlist.user_id', '=', 'shops.user_id')
            ->pluck('shops.id')
            ->toArray();

        $shopList = array_unique(array_merge($interestedShops, $wishlistShops));

        // 🔹 Get pending offers sent by this user for this product
        $pendingOffers = Offer::where('product_id', $productId)
            ->where('is_owner_offer', true)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
            })
            ->pluck('buyer_id') // get the user IDs of buyers
            ->toArray();

        // 🔹 Get shop IDs of those users
        $blockedShopIds = Shop::whereIn('user_id', $pendingOffers)
            ->pluck('id')
            ->toArray();

        // 🔹 Only block shops that actually are in wishlist/interested for this product
        $blockedShopIds = array_intersect($blockedShopIds, $shopList);

        // 🔹 Final shops list
        $finalShops = array_diff($shopList, $blockedShopIds);

        $shops = Shop::whereIn('id', $finalShops)
            ->select('id', 'name')
            ->orderBy('id', 'asc')
            ->get();

        // 🔹 Add shop rating info
        $shopsWithRating = $shops->map(function ($shop) {
            $ratingStats = Review::whereHas('product', function ($q) use ($shop) {
                $q->where('shop_id', $shop->id);
            })->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')->first();

            $shop->average_rating = $ratingStats->avg_rating ? round($ratingStats->avg_rating, 1) : 0;
            $shop->total_reviews = $ratingStats->total_reviews ?? 0;

            return $shop;
        });

        return response()->json([
            'message' => 'Shops fetched successfully',
            'data' => $shops
        ]);
    }

    public function shopProductReviews($shopId)
    {
        $shop = Shop::with(['products.reviews.user'])->findOrFail($shopId);

        $reviews = [];
        $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $totalRating = 0;
        $totalReviews = 0;

        foreach ($shop->products as $product) {
            foreach ($product->reviews as $review) {

                $totalReviews++;
                $totalRating += $review->rating;
                $ratingCounts[$review->rating]++;

                $reviews[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'rating'       => $review->rating,
                    'review'       => $review->review,
                    'user' => [
                        'id'       => $review->user->id,
                        'username' => $review->user->username,
                    ],
                    'created_at' => $review->created_at,
                ];
            }
        }

        // 🔢 Calculations
        $averageRating = $totalReviews > 0
            ? round($totalRating / $totalReviews, 1)
            : 0;

        // Percentage out of 5 stars
        $ratingPercentage = $totalReviews > 0
            ? round(($averageRating / 5) * 100)
            : 0;

        // ⭐ Breakdown percentages
        $breakdown = [];
        foreach ($ratingCounts as $star => $count) {
            $breakdown[$star] = [
                'count' => $count,
                'percentage' => $totalReviews > 0
                    ? round(($count / $totalReviews) * 100)
                    : 0
            ];
        }

        return response()->json([
            'shop_id'   => $shop->id,
            'shop_name' => $shop->name,
            'stats' => [
                'total_reviews'    => $totalReviews,
                'average_rating'   => $averageRating,
                'rating_percentage'=> $ratingPercentage,
                'breakdown'        => $breakdown,
            ],
            'reviews' => $reviews,
        ]);
    }


    public function addProductReview(Request $request, $productId)
    {
        $user = $request->user();

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        $product = Product::with('shop')->findOrFail($productId);

        // ❌ Prevent reviewing own shop product
        if ($product->user_id === $user->id) {
            return response()->json([
                'message' => 'You cannot review your own product'
            ], 403);
        }

        $review = \App\Models\Review::updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id'    => $user->id,
            ],
            [
                'rating'     => $request->rating,
                'review'     => $request->review,
                'ip_address' => $request->ip(),
            ]
        );

        return response()->json([
            'message' => 'Product review submitted successfully',
            'data'    => $review,
        ]);
    }



    public function likedProductsOfShop(Request $request, $shopId)
    {
        $user = $request->user();

        $shop = Shop::findOrFail($shopId);

        // Products of this shop that THIS user has wishlisted
        $products = Product::where('shop_id', $shop->id)
            ->whereHas('wishlistedBy', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->with([
                'shop:id,name',
            ])
            ->select('id', 'shop_id', 'slug', 'price')
            ->get();

        return response()->json([
            'shop_id'   => $shop->id,
            'shop_name' => $shop->name,
            'total'     => $products->count(),
            'data'      => $products,
        ]);
    }

    public function toggleVacationMode(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'vacation_mode' => 'required|boolean',
        ]);

        $shop = Shop::where('user_id', $user->id)->firstOrFail();

        $shop->update([
            'vacation_mode' => $data['vacation_mode'],
        ]);

        return response()->json([
            'success' => true,
            'vacation_mode' => $shop->vacation_mode,
            'message' => $shop->vacation_mode
                ? 'Vacation mode enabled'
                : 'Vacation mode disabled',
        ]);
    }

    public function shareLink(Request $request)
    {
        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->first();

        if (!$shop) {
            return response()->json([
                'message' => 'Shop not found'
            ], 404);
        }

        // Public shareable link
        $shareLink = config('app.frontend_url') . '/api/v1/shop/' . $shop->id;

        return response()->json([
            'shop_id' => $shop->id,
            'share_link' => $shareLink
        ]);
    }






}
