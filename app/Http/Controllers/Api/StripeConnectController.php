<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransfer;
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




    public function getOnboardingUrl(Request $request)
    {
        $user = $request->user();

        $shop = Shop::where('user_id', $user->id)->first();

        if (!$shop || !$shop->stripe_account_id) {
            return response()->json([
                'message' => 'Stripe account not found'
            ], 404);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Retrieve account to check onboarding status
        $account = \Stripe\Account::retrieve($shop->stripe_account_id);

        if (
            $account->charges_enabled &&
            $account->payouts_enabled &&
            empty($account->requirements->currently_due)
        ) {
            return response()->json([
                'onboarding_required' => false,
                'message' => 'Stripe onboarding already completed'
            ]);
        }

        // Only generate onboarding link, do NOT create account
        $accountLink = \Stripe\AccountLink::create([
            'account' => $shop->stripe_account_id,
            'type' => 'account_onboarding',
            'refresh_url' => config('app.frontend_url') . '/stripe/onboard/refresh?shop_id=' . $shop->id,
            'return_url'  => config('app.frontend_url') . '/stripe/onboard/complete?shop_id=' . $shop->id,
        ]);

        return response()->json([
            'onboarding_required' => true,
            'url' => $accountLink->url,
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


    public function checkOrCreateStripeAccount(Request $request)
    {
        $user = $request->user();

        // Get user's shop
        $shop = Shop::where('user_id', $user->id)->first();
        if (!$shop) {
            return response()->json(['message' => 'Shop not found for user'], 404);
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        // Step 1: Create Stripe account if not exists
        if (!$shop->stripe_account_id) {
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => env('STRIPE_COUNTRY', 'AE'),
                'email' => $user->email,
                'business_type' => 'company',
                'metadata' => ['shop_id' => $shop->id],
            ]);

            $shop->stripe_account_id = $account->id;
            $shop->save();
        } else {
            // Retrieve existing account
            $account = \Stripe\Account::retrieve($shop->stripe_account_id);
        }

        // Step 2: Check if onboarding is completed
        $onboardingCompleted = $account->charges_enabled
                            && $account->payouts_enabled
                            && empty($account->requirements->currently_due);

        $onboardingRequired = !$onboardingCompleted;

        $onboardingUrl = null;

        // Step 3: If onboarding not completed, create onboarding link
        if ($onboardingRequired) {
            $accountLink = \Stripe\AccountLink::create([
                'account' => $shop->stripe_account_id,
                'type' => 'account_onboarding',
                'refresh_url' => config('app.frontend_url') . '/stripe/onboard/refresh?shop_id=' . $shop->id,
                'return_url'  => config('app.frontend_url') . '/stripe/onboard/complete?shop_id=' . $shop->id,
            ]);

            $onboardingUrl = $accountLink->url;
        }

        return response()->json([
            'stripe_account_id' => $shop->stripe_account_id,
            'onboarding_required' => $onboardingRequired,
            'onboarding_url' => $onboardingUrl,
            'stripe_account' => $account,
        ]);
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


    public function balance(Request $request)
    {
        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->firstOrFail();

        if (!$shop->stripe_account_id) {
            return response()->json([
                'connected' => false,
                'message' => 'Stripe not connected'
            ]);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 1️⃣ Retrieve balance
        $balance = \Stripe\Balance::retrieve([
            'stripe_account' => $shop->stripe_account_id,
        ]);

        // 2️⃣ Retrieve account details
        $account = \Stripe\Account::retrieve(
            $shop->stripe_account_id
        );

        // 3️⃣ Resolve account holder name (Express-safe)
        $accountHolderName =
            $account->business_profile->name
            ?? trim(($account->individual->first_name ?? '') . ' ' . ($account->individual->last_name ?? ''))
            ?? $account->email
            ?? 'Account Holder';

        return response()->json([
            'connected' => true,

            // 👤 Account info (for UI card)
            'account' => [
                'name' => $accountHolderName,
                'email' => $account->email,
                'country' => $account->country,
                'currency' => strtoupper($account->default_currency ?? 'AED'),
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
            ],

            // 💰 Balance info
            'balance' => [
                'available' => collect($balance->available)->map(fn ($b) => [
                    'amount' => $b->amount / 100,
                    'currency' => strtoupper($b->currency),
                ])->values(),

                'pending' => collect($balance->pending)->map(fn ($b) => [
                    'amount' => $b->amount / 100,
                    'currency' => strtoupper($b->currency),
                ])->values(),
            ],
        ]);
    }


    public function transactions(Request $request)
    {
        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->firstOrFail();

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $data = collect();

        // ================= STRIPE CONNECT TRANSACTIONS =================
        try {
            $stripeTransactions = \Stripe\BalanceTransaction::all([
                'limit' => 20,
            ], [
                'stripe_account' => $shop->stripe_account_id,
            ]);

            $stripeData = collect($stripeTransactions->data)->map(function ($t) {
                $message = 'Transaction';

                if ($t->type === 'payment') {
                    $message = $t->status === 'pending'
                        ? 'Payment received (processing)'
                        : 'Payment received';
                } elseif ($t->type === 'payout') {
                    $message = match ($t->status) {
                        'pending' => 'Withdrawal in progress',
                        'paid'    => 'Withdrawal completed',
                        'failed'  => 'Withdrawal failed',
                        default   => 'Withdrawal',
                    };
                } elseif ($t->type === 'stripe_fee') {
                    $message = 'Platform fee deducted';
                } elseif ($t->type === 'transfer') {
                    $message = 'Funds transferred';
                }

                return [
                    'source' => 'stripe',
                    'id' => $t->id,
                    'message' => $message,
                    'type' => $t->type === 'payment' ? 'credit' : 'debit',
                    'amount' => abs($t->amount) / 100,
                    'currency' => strtoupper($t->currency),
                    'status' => $t->status,
                    'created_at' => date('Y-m-d H:i:s', $t->created),
                ];
            });

            $data = $data->merge($stripeData);
        } catch (\Exception $e) {
            \Log::error('Stripe transactions fetch failed: ' . $e->getMessage());
        }

        // ================= MARKETPLACE PAYMENTS (PaymentTransfer) =================
        $marketplaceData = PaymentTransfer::with(['order.orderProducts', 'shop', 'buyer'])
            ->where(function ($q) use ($shop, $user) {
                $q->where('shop_id', $shop->id) // received payments
                ->orWhereHas('order', function ($q2) use ($user) {
                    $q2->where('buyer_id', $user->id); // payments sent
                });
            })
            ->whereIn('status', ['on_hold', 'released']) // ✅ only on_hold or released
            ->get()
            ->map(function ($pt) use ($user) {

                $order = $pt->order;
                $sellerShop = $pt->shop; // receiver
                $buyer = $pt->buyer ?? optional($order)->buyer; // sender

                $isSender = optional($order)->buyer_id === $user->id;

                $productTitle = optional($order?->orderProducts->first())?->product_title
                                ?? $pt->meta['product_title'] 
                                ?? 'Unknown Product';

                $buyerName = $buyer?->name 
                            ?? ($buyer?->first_name && $buyer?->last_name ? $buyer->first_name . ' ' . $buyer->last_name : null)
                            ?? 'Unknown Buyer';

                $shopName = $sellerShop?->name ?? 'Unknown Shop';

                // Build base transaction array
                $transaction = [
                    'source' => 'marketplace',
                    'id' => $pt->id,
                    'message' => $isSender
                        ? 'Payment sent to ' . $shopName . ' for ' . $productTitle
                        : 'Payment received from ' . $buyerName . ' for ' . $productTitle,
                    'type' => $isSender ? 'Outgoing' : 'Incoming',
                    'amount' => ($pt->checkout_amount_cents ?? 0) / 100,
                    'currency' => strtoupper($pt->checkout_currency ?? 'AED'),
                    'status' => $pt->status ?? 'on_hold',
                    'created_at' => $pt->created_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];

                // If payment is on_hold, include release date
                if ($pt->status === 'on_hold' && $pt->release_at) {
                    $transaction['release_at'] = $pt->release_at->toDateTimeString();
                }

                return $transaction;
            });

        $data = $data->merge($marketplaceData);

        // Sort by created_at descending
        $data = $data->sortByDesc('created_at')->values();

        return response()->json($data);
    }





    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $user = $request->user();
        $shop = Shop::where('user_id', $user->id)->firstOrFail();

        Stripe::setApiKey(env('STRIPE_SECRET'));

        $payout = \Stripe\Payout::create([
            'amount' => intval($request->amount * 100),
            'currency' => 'aed',
        ], [
            'stripe_account' => $shop->stripe_account_id,
        ]);

        return response()->json([
            'message' => 'Withdrawal requested',
            'payout_id' => $payout->id,
            'status' => $payout->status,
        ]);
    }



}
