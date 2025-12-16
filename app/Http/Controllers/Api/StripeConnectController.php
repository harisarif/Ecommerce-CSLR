<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use App\Models\Shop;

class StripeConnectController extends Controller
{
    public function createExpressAccount(Request $request)
    {
        $user = $request->user();

        // Load shop - adjust as needed to find the shop record
        $shop = Shop::where('user_id', $user->id)->first();
        if (!$shop) {
            return response()->json(['message' => 'Shop not found for user'], 404);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // If shop already has a stripe account id, just create an account link
        if (!$shop->stripe_account_id) {
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => env('STRIPE_COUNTRY', 'AE'), // adapt as needed
                'email' => $user->email,
                'business_type' => 'company',
                'metadata' => ['shop_id' => $shop->id],
            ]);

            $shop->stripe_account_id = $account->id;
            $shop->save();
        }

        // Create account link for onboarding
        $accountLink = \Stripe\AccountLink::create([
            'account' => $shop->stripe_account_id,
            'refresh_url' => config('app.frontend_url') . '/stripe/onboard/refresh?shop_id=' . $shop->id,
            'return_url'  => config('app.frontend_url') . '/stripe/onboard/complete?shop_id=' . $shop->id,
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'url' => $accountLink->url,
            'stripe_account_id' => $shop->stripe_account_id,
        ]);
    }

    public function getOnboardingStatus(Request $request)
    {
        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->first();
        if (!$shop) return response()->json(['connected' => false]);

        Stripe::setApiKey(env('STRIPE_SECRET'));
        try {
            $acct = \Stripe\Account::retrieve($shop->stripe_account_id);
            $connected = ($acct && $acct->charges_enabled);
            return response()->json([
                'connected' => $connected,
                'account' => $acct,
            ]);
        } catch (\Exception $e) {
            return response()->json(['connected' => false, 'error' => $e->getMessage()], 200);
        }
    }

    public function createLoginLink(Request $request)
    {
        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->first();
        if (!$shop || !$shop->stripe_account_id) {
            return response()->json(['message' => 'Stripe account not found'], 404);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $loginLink = \Stripe\Account::createLoginLink($shop->stripe_account_id);
            return response()->json([
                'login_url' => $loginLink->url
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Check if Stripe Connect is enabled for the user's shop
     */
    public function isStripeEnabled(Request $request)
    {
        $user = $request->user();
        
        // Load the user's shop
        $shop = Shop::where('user_id', $user->id)->first();
        
        if (!$shop || !$shop->stripe_account_id) {
            return response()->json([
                'stripe_enabled' => false
            ]);
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            $account = \Stripe\Account::retrieve($shop->stripe_account_id);

            return response()->json([
                'stripe_enabled' => $account->charges_enabled ?? false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'stripe_enabled' => false,
                'error' => $e->getMessage()
            ]);
        }
    }


}
