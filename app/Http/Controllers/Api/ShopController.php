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
use App\Models\Product;
use App\Models\ProductInterested;
use App\Models\Wishlist;
use App\Notifications\ShopFollowNotification;
// use Illuminate\Support\Facades\Notification;

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

        return response()->json([
            'data' => $shop,
            'followers_count' => $shop->followers_count,
            'average_rating' => $shop->reviews_avg_rating ? round($shop->reviews_avg_rating, 1) : null,
            'reviews_count' => $shop->reviews_count,
        ]);
    }


    // public shop page by id (or slug)
    public function show($id)
    {
        $shop = is_numeric($id)
            ? Shop::with('products')
                ->withCount(['followers', 'reviews']) // ✅ include reviews_count directly
                ->withAvg('reviews', 'rating')
                ->find($id)
            : Shop::with('products')
                ->withCount(['followers', 'reviews'])
                ->withAvg('reviews', 'rating')
                ->where('slug', $id)
                ->first();

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        return response()->json([
            'data' => $shop,
            'followers_count' => $shop->followers_count,
            'average_rating' => $shop->reviews_avg_rating ? round($shop->reviews_avg_rating, 1) : null,
            'reviews_count' => $shop->reviews_count, // ✅ directly from withCount
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

        $shop->followers()->syncWithoutDetaching([$request->user()->id]);
        
         // ✅ Create notification record manually
        Notification::create([
            'type' => 'shop_follow',
            'notifiable_type' => get_class($shop->user),
            'notifiable_id' => $shop->user->id,
            'data' => [
                'type' => 'shop_follow',
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'follower_id' => $user->id,
                'follower_username' => $user->username,
                'message' => "{$user->username} followed your shop \"{$shop->name}\"",
            ],
        ]);


        // ✅ Send realtime push manually via your existing helper
        $channel = "private-user-{$shop->user->id}";
        $event = "shop-follow";
        $payload = [
            'type' => 'shop_follow',
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'follower_id' => $user->id,
            'follower_username' => $user->username,
            'message' => "{$user->username} followed your shop \"{$shop->name}\"",
        ];

        PusherHelper::trigger($channel, $event, $payload);

        return response()->json(['message' => 'Followed shop']);
    }

    // ✅ Unfollow Shop
    public function unfollow(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);

        $shop->followers()->detach($request->user()->id);

        $user = $request->user();
        
        // ✅ Create notification record manually
        Notification::create([
            'type' => 'shop_unfollow',
            'notifiable_type' => get_class($shop->user),
            'notifiable_id' => $shop->user->id,
            'data' => [
                'type' => 'shop_unfollow',
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'follower_id' => $user->id,
                'follower_username' => $user->username,
                'message' => "{$user->username} unfollowed your shop \"{$shop->name}\"",
            ],
        ]);

        // ✅ Send realtime push manually via your existing helper
        $channel = "private-user-{$shop->user->id}";
        $event = "shop-follow";
        $payload = [
            'type' => 'shop_follow',
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'follower_id' => $user->id,
            'follower_username' => $user->username,
            'message' => "{$user->username} followed your shop \"{$shop->name}\"",
        ];

        PusherHelper::trigger($channel, $event, $payload);

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








}
