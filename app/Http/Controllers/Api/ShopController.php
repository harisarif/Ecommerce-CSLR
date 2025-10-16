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
use App\Notifications\ShopFollowNotification;
use Illuminate\Support\Facades\Notification;

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
        
        // ✅ Store notification in DB
        $notification = new ShopFollowNotification($user, $shop, 'follow');
        $shop->user->notify($notification);

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
        // ✅ Store notification in DB
        $notification = new ShopFollowNotification($user, $shop, 'follow');
        $shop->user->notify($notification);

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
}
