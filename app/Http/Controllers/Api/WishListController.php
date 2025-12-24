<?php

namespace App\Http\Controllers\Api;

use App\Helpers\FcmHelper;
use App\Helpers\PusherHelper;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class WishListController extends Controller
{
    public function getWhishlistProductsByUser(){
        $product = Wishlist::with(['product.details', 'product.licenseKeys', 'product.searchIndexes', 'product.appCategory', 'product.user', 'product.variations', 'product.defaultVariationOptions',])
        ->where('user_id', Auth::user()->id)
        ->paginate(10);
        return response()->json($product);
    }

     // ✅ LIKE / ADD TO WISHLIST
    public function addToWishlist($product_id)
    {
        $user = Auth::user();

        if (Wishlist::isInWishlist($user->id, $product_id)) {
            return response()->json(['message' => 'Product already in wishlist']);
        }

        $product = Product::with('user')->findOrFail($product_id);
        $recipient = $product->user;

        // Save wishlist
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // ❌ Don't notify if user likes own product
        if ($recipient && $recipient->id !== $user->id) {

            $notificationText = "{$user->username} liked your product \"{$product->title}\"";

            // ✅ Save notification
            Notification::create([
                'type' => 'product_like',
                'notifiable_type' => get_class($recipient),
                'notifiable_id' => $recipient->id,
                'data' => [
                    'type' => 'product_like',
                    'product_id' => $product->id,
                    'product_title' => $product->title,
                    'liker_id' => $user->id,
                    'liker_username' => $user->username,
                    'message' => $notificationText,
                ],
            ]);

            // ✅ Pusher realtime
            PusherHelper::trigger(
                "private-notifications.{$recipient->id}",
                'product-like',
                [
                    'type' => 'product_like',
                    'title' => 'Product Liked',
                    'body' => $notificationText,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'image' => $product->image_url ?? null,
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
                    'Product Liked ❤️',
                    $notificationText,
                    [
                        'type' => 'product_like',
                        'product_id' => $product->id,
                    ],
                    $product->id,
                    'product_like'
                );
            }
        }

        return response()->json($wishlist);
    }

    // ❌ UNLIKE / REMOVE FROM WISHLIST
    public function removeFromWishlist($product_id)
    {
        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product_id)
            ->firstOrFail();

        $product = Product::with('user')->find($product_id);
        $recipient = $product?->user;

        $wishlist->delete();

        if ($recipient && $recipient->id !== $user->id) {

            $notificationText = "{$user->username} unliked your product \"{$product->title}\"";

            // ✅ Save notification
            Notification::create([
                'type' => 'product_unlike',
                'notifiable_type' => get_class($recipient),
                'notifiable_id' => $recipient->id,
                'data' => [
                    'type' => 'product_unlike',
                    'product_id' => $product->id,
                    'product_title' => $product->title,
                    'unliker_id' => $user->id,
                    'unliker_username' => $user->username,
                    'message' => $notificationText,
                ],
            ]);

            // ✅ Pusher realtime
            PusherHelper::trigger(
                "private-notifications.{$recipient->id}",
                'product-unlike',
                [
                    'type' => 'product_unlike',
                    'title' => 'Product Unliked',
                    'body' => $notificationText,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'image' => $product->image_url ?? null,
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
                    'Product Unliked',
                    $notificationText,
                    [
                        'type' => 'product_unlike',
                        'product_id' => $product->id,
                    ],
                    $product->id,
                    'product_unlike'
                );
            }
        }

        return response()->json(['message' => 'Product removed from wishlist']);
    }
}
