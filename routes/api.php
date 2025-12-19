<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WishListController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\DiscoverController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\StripeCheckoutController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('register-vendor', [AuthController::class, 'registerVendor']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('/email-login-request', [AuthController::class, 'emailLoginRequest']);
        Route::get('/email-login-verify', [AuthController::class, 'emailLoginVerify']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::get('/categories-with-sizes', [CategoryController::class, 'getCategoriesWithSizes']);
        Route::post('/resend-email-login', [AuthController::class, 'resendEmailLogin']);
        Route::get('/getProductMeta', [CategoryController::class, 'getProductMeta']);
    });
    Route::prefix('category')->group(function () {
        Route::get('/list', [CategoryController::class, 'index']); // paginate list
        Route::post('/', [CategoryController::class, 'store']); // create parent/child/sub
        Route::get('/tree', [CategoryController::class, 'tree']); // full hierarchy
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });
    Route::prefix('brands')->group(function () {
        Route::get('/list', [BrandController::class, 'index']);
    });
    
    Route::prefix('product')->group(function () {
        Route::get('/special-offers', [ProductController::class, 'getSpecialOfferProducts']);
        Route::get('/promoted', [ProductController::class, 'getPromotedProducts']);
        Route::get('/category/{category_id}', [ProductController::class, 'getProductsByCategory']);
        Route::get('/search', [ProductController::class, 'search']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::post('stripe/connect/create', [\App\Http\Controllers\Api\StripeConnectController::class, 'createExpressAccount']);
        Route::get('stripe/connect/status', [\App\Http\Controllers\Api\StripeConnectController::class, 'getOnboardingStatus']);
        Route::get('stripe/connect/login-link', [\App\Http\Controllers\Api\StripeConnectController::class, 'createLoginLink']);
        Route::get('/stripe/enabled', [\App\Http\Controllers\Api\StripeConnectController::class, 'isStripeEnabled']);
        Route::get('stripe/balance', [\App\Http\Controllers\Api\StripeConnectController::class, 'balance']);
        Route::get('stripe/transactions', [\App\Http\Controllers\Api\StripeConnectController::class, 'transactions']);
        Route::post('stripe/withdraw', [\App\Http\Controllers\Api\StripeConnectController::class, 'withdraw']);
        Route::post('/checkout/session', [StripeCheckoutController::class, 'createCheckoutSession']);
        Route::post('product/create', [ProductController::class, 'store']);
        Route::get('/products/filter', [DiscoverController::class, 'getFilteredProducts']);
        Route::get('/filters', [DiscoverController::class, 'filters']);
        Route::prefix('user')->group(function () {
            Route::get('/logout', [AuthController::class, 'logout']);
            Route::get('/index', [UserController::class, 'index']);
            Route::post('/update-profile', [UserController::class, 'updateProfile']);
            Route::post('/change-password', [UserController::class, 'changePassword']);
            Route::get('/check-token', [AuthController::class, 'checkToken']);
            Route::get('/profile', [UserController::class, 'getUserProfile']);
            Route::post('/profile/update', [UserController::class, 'updateUserProfile']);
        });

        
        Route::prefix('product')->group(function () {
            Route::get('/list', [ProductController::class, 'getUserProducts']);
            Route::get('/{id}/detail', [ProductController::class, 'getProductWithShop']);
        });

        Route::prefix('cart')->group(function () {
            Route::get('/index', [CartController::class, 'index']);
            Route::post('/add', [CartController::class, 'add']);
            Route::delete('/remove', [CartController::class, 'remove']);
            Route::delete('/clear', [CartController::class, 'clear']);
            Route::post('/checkout', [CartController::class, 'checkout']);
            Route::post('/update-quantity', [CartController::class, 'updateQuantity']);
        });
        Route::prefix('wishlist')->group(function () {
            Route::get('/add/{product_id}', [WishListController::class, 'addToWishlist']);
            Route::get('/remove/{product_id}', [WishListController::class, 'removeFromWishlist']);
            Route::get('/list', [WishListController::class, 'getWhishlistProductsByUser']);
        });
        Route::prefix('order')->group(function () {
            Route::post('/checkout', [OrderController::class, 'createOrder']);
            Route::get('/getOrders', [OrderController::class, 'getUserOrders']);
            Route::post('/cancel', [OrderController::class, 'cancelOrder']);
        });

        // Shops
        Route::prefix('shop')->group(function () {
            Route::post('/create', [ShopController::class, 'store']);
            Route::get('/my-shop', [ShopController::class, 'myShop']);
            Route::get('/list', [ShopController::class, 'shopsList']);
            Route::get('/{id}', [ShopController::class, 'show']);


            Route::post('{id}/review', [ShopController::class, 'addReview']);
            Route::post('{id}/addproduct-review', [ShopController::class, 'addProductReview']);
            Route::get('{id}/reviews', [ShopController::class, 'getReviews']);

            Route::post('{id}/follow', [ShopController::class, 'follow']);
            Route::post('{id}/unfollow', [ShopController::class, 'unfollow']);
            Route::get('{id}/is-following', [ShopController::class, 'isFollowing']);
            Route::get('{id}/product-reviews', [ShopController::class, 'shopProductReviews']);
        });   

        // Offers
        Route::prefix('offer')->group(function () {
            Route::post('/create', [OfferController::class, 'store']);
            Route::get('/sent', [OfferController::class, 'sent']);
            Route::get('/received', [OfferController::class, 'received']);
            Route::put('/{id}', [OfferController::class, 'update']);
            Route::post('/{id}/counter', [OfferController::class, 'counterOffer']);
            Route::get('/sent-multiple', [OfferController::class, 'sentMultiple']);
            Route::get('/received-multiple', [OfferController::class, 'receivedMultiple']);
        });   

        // Inbox
        Route::prefix('chat')->group(function () {
            Route::get('/', [InboxController::class, 'index']);
            Route::post('/send', [InboxController::class, 'sendMessage']);
            Route::get('/thread', [InboxController::class, 'chatThread']);
        });   

        //Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread/count', [NotificationController::class, 'unreadCount']);
            Route::get('/unread', [NotificationController::class, 'unread']);

            Route::post('/read/{id}', [NotificationController::class, 'markAsRead']);
            Route::post('/read', [NotificationController::class, 'markMultipleAsRead']);

            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::delete('/', [NotificationController::class, 'destroyMultiple']);
            Route::delete('/clear/all', [NotificationController::class, 'clearAll']);

        });
           
    });
    Route::post('/check-username', [UserController::class, 'checkUsername']);
    Route::get('/currency/active', [UserController::class, 'getActive']);
    Route::apiResource('sizes', SizeController::class);
    Route::post('/stripe/webhook', [StripeCheckoutController::class, 'handleStripeWebhook']);

});
