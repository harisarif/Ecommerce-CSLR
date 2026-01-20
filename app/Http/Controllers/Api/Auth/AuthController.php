<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use App\Notifications\PasswordResetNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\EmailLoginToken;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailLoginLinkMail;
use App\Models\UserBrand;
use App\Models\UserSize;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;


class AuthController extends Controller
{

    public function emailLoginRequest(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'fcm_token' => 'nullable|string',
        ]);

        $email = $request->email;
        $token = Str::random(64);

        // Save FCM token if user exists already
        $user = User::where('email', $email)->first();
        if ($user) {
            if ($request->filled('fcm_token')) {
                $user->fcm_token = $request->fcm_token;
                $user->save();

                \Log::info("📲 FCM Token Saved in emailLoginRequest", [
                    'email' => $email,
                    'fcm_token' => $request->fcm_token
                ]);
            } else {
                \Log::warning("⚠ No FCM token provided in emailLoginRequest", [
                    'email' => $email
                ]);
            }
        } else {
            \Log::info("🆕 New user login request — no FCM save needed yet", [
                'email' => $email
            ]);
        }


        EmailLoginToken::create([
            'email' => $email,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

       $link = url("/api/v1/auth/email-login-verify?token=$token");

        // Send styled email
        Mail::to($email)->send(new EmailLoginLinkMail($link));

        $exists = User::where('email', $email)->exists();

        return response()->json([
            'message' => 'Verification link sent to your email',
            'is_new_user' => !$exists,
        ]);
    }

    public function resendEmailLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Revoke any old tokens for this email
        EmailLoginToken::where('email', $email)->delete();

        // Generate new token
        $token = Str::random(64);

        EmailLoginToken::create([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

        $link = url("/api/v1/auth/email-login-verify?token=$token");

        Mail::to($email)->send(new EmailLoginLinkMail($link));

        return response()->json([
            'message' => 'A new verification link has been sent to your email',
        ]);
    }

    public function emailLoginVerify(Request $request)
    {
        $request->validate([
            'fcm_token' => 'nullable|string', // <-- accept token from app
        ]);

        $token = $request->query('token');
        $record = EmailLoginToken::where('token', $token)->first();

        if (!$record || $record->isExpired()) {
            return response()->json(['message' => 'Invalid or expired token'], 422);
        }

        $user = User::where('email', $record->email)->first();


        if ($user) {
            if ($request->filled('fcm_token')) {
                $user->fcm_token = $request->fcm_token;
                $user->save();

                \Log::info("📲 FCM Token Saved in emailLoginVerify", [
                    'email' => $user->email,
                    'fcm_token' => $request->fcm_token
                ]);
            } else {
                \Log::warning("⚠ No FCM token received in emailLoginVerify", [
                    'email' => $user->email
                ]);
            }

            $apiToken = JWTAuth::fromUser($user);
            $record->delete();

            return response()->json([
                'status' => 'success',
                'token' => $apiToken,
                'email' => $user->email
            ]);
        } else {

            \Log::info("🆕 New user verified — pending registration", [
                'email' => $record->email
            ]);

            return response()->json([
                'status' => 'new_user',
                'email' => $record->email
            ]);
        }

        // if ($user) {
        //     $apiToken = $user->createToken('auth-token')->plainTextToken;
        //     $record->delete();

        //     return redirect()->away("https://lightgray-dragonfly-620192.hostingersite.com/auth?status=success&token=$apiToken&email={$user->email}");
        // } else {
        //     return redirect()->away("https://lightgray-dragonfly-620192.hostingersite.com/auth?status=new_user&email={$record->email}");
        // }
    }

    public function checkToken(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['status' => 'invalid', 'message' => 'Token is invalid or expired'], 401);
            }

            return response()->json([
                'status' => 'valid',
                'message' => 'Token is still valid',
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Token check failed',
            ], 401);
        }
    }


    public function registerVendor(Request $request)
    {
        $request->validate([
            'first_name'      => 'required|string|max:255',
            'last_name'       => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users',
            'dob'             => 'required|date',
            'username'        => 'required|string|max:255|unique:users',
            'billing_address' => 'required|string',
            'fcm_token'       => 'nullable|string',
            'sizes'           => 'array',
            'sizes.*.category_id' => 'required_with:sizes|exists:categories,id',
            'sizes.*.size_id'     => 'required_with:sizes|exists:sizes,id',
            'brands'          => 'array',
            'brands.*'        => 'required|exists:brands,id',
        ]);

        // Create user
        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'dob'             => $request->dob,
            'username'        => $request->username,
            'billing_address' => $request->billing_address,
            'role_id'         => 2, // default role
            'fcm_token'       => $request->fcm_token ?? null,
        ]);


        // ✅ Create dummy shop automatically
        $shopName = $user->first_name . "'s Shop";
        $slug = Str::slug($shopName);

        // Make sure slug is unique
        $existingSlugCount = \App\Models\Shop::where('slug', $slug)->count();
        if ($existingSlugCount > 0) {
            $slug .= '-' . Str::random(4);
        }

        $shop = \App\Models\Shop::create([
            'user_id'     => $user->id,
            'name'        => $shopName,
            'slug'        => $slug,
            'description' => 'Welcome to ' . $shopName . '!',
            'phone'       => null,
            'address'     => $user->billing_address,
            'settings'    => [],
        ]);

        // ================== STRIPE EXPRESS ACCOUNT CREATION ==================
        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => env('STRIPE_COUNTRY', 'AE'), // change if needed
                'email' => $user->email,
                'metadata' => [
                    'user_id' => $user->id,
                    'shop_id' => $shop->id,
                ],
            ]);

            // Save Stripe account ID in shop
            $shop->stripe_account_id = $account->id;
            $shop->save();

        } catch (\Exception $e) {
            // Optional: log error (DO NOT block signup)
            \Log::error('Stripe account creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
        }
        // Save sizes (store IDs)
        if ($request->has('sizes')) {
            foreach ($request->sizes as $sizeData) {
                   UserSize::create([
                    'user_id'     => $user->id,
                    'app_category_id' => $sizeData['category_id'],
                    'size_id'     => $sizeData['size_id'],
                ]);
            }
        }

        // Save brands (store IDs)
        if ($request->has('brands')) {
            foreach ($request->brands as $brandId) {
                UserBrand::create([
                    'user_id'  => $user->id,
                    'brand_id' => $brandId,
                ]);
            }
        }

       $token = JWTAuth::fromUser($user);

        return response()->json([
            'message'      => 'User registered successfully',
            'user'         => $user,
            'access_token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:4|confirmed',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }




    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'message' => 'User logged in successfully',
            'user' => auth('api')->user(),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }



    /**
     * Send password reset code to user's email
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $email = $request->email;

            // Check if there's a recent request to prevent spam
            $recentRequest = PasswordReset::where('email', $email)
                ->where('created_at', '>', now()->subMinutes(2))
                ->exists();

            if ($recentRequest) {
                return response()->json([
                    'message' => 'A password reset code was recently sent. Please wait before requesting another.',
                    'retry_after' => 120 // seconds
                ], 429);
            }

            // Generate a 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(30);

            Log::info('Initiating password reset', [
                'email' => $email,
                'code' => $code,
                'expires_at' => $expiresAt
            ]);

            // Store or update the code in the password_resets table
            PasswordReset::updateOrCreate(
                ['email' => $email],
                [
                    'code' => $code,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                ]
            );

            // Send the code via email
            $user = User::where('email', $email)->firstOrFail();

            // Queue the notification
            $user->notify(new PasswordResetNotification($code));

            Log::info('Password reset code sent', [
                'email' => $email,
                'user_id' => $user->id,
                'expires_at' => $expiresAt->toDateTimeString()
            ]);

            return response()->json([
                'message' => 'Password reset code sent to your email',
                'expires_at' => $expiresAt->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Password reset request failed', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to process password reset request. Please try again later.'
            ], 500);
        }
    }

    /**
     * Verify password reset code
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
        ]);

        $passwordReset = PasswordReset::where('email', $request->email)
            ->where('code', $request->code)
            ->first();

        if (!$passwordReset || $passwordReset->isExpired()) {
            return response()->json([
                'message' => 'Invalid or expired reset code',
            ], 422);
        }

        // Generate a one-time token for password reset
        $token = Str::random(60);
        $passwordReset->update([
            'token' => $token,
            'expires_at' => Carbon::now()->addMinutes(10), // Token expires in 10 minutes
        ]);

        return response()->json([
            'message' => 'Reset code verified',
            'reset_token' => $token,
            'expires_at' => $passwordReset->expires_at->toDateTimeString(),
        ]);
    }

    /**
     * Reset user's password
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $passwordReset = PasswordReset::where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$passwordReset || $passwordReset->isExpired()) {
            return response()->json([
                'message' => 'Invalid or expired reset token',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // delete reset records
        PasswordReset::where('email', $request->email)->delete();

        // ✅ No Sanctum tokens to delete here!

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message'      => 'Password has been reset successfully',
            'user'         => $user,
            'access_token' => $token,
        ]);
    }



    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'User logged out successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to logout, token invalid'], 500);
        }
    }

}
