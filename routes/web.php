<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Offer;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Shop;
use Stripe\Stripe;
use Stripe\AccountLink;
use Illuminate\Support\Facades\Artisan;
use App\Models\PaymentTransfer;

Route::get('/', function () {
    return view('welcome');
});


    Route::get('/order-success', function (Request $request) {
        $sessionId = $request->get('session_id');
        return response()->view('stripe.order-success', compact('sessionId'));
    })->name('order.success');

    Route::get('/checkout-cancelled', function () {
        return response()->view('stripe.checkout-cancelled');
    })->name('order.cancel');



    Route::get('/logs', function () {
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            return "Log file not found.";
        }

        // Read last 500 lines
        $lines = [];
        $fp = fopen($logFile, "r");
        fseek($fp, -1, SEEK_END);

        $buffer = '';
        $lineCount = 0;

        while (ftell($fp) > 0 && $lineCount < 500) {
            $char = fgetc($fp);

            if ($char === "\n") {
                $lines[] = strrev($buffer);
                $buffer = '';
                $lineCount++;
            } else {
                $buffer .= $char;
            }

            fseek($fp, ftell($fp) - 2);
        }

        fclose($fp);

        // Add last line if not counted
        if ($buffer !== '' && $lineCount < 500) {
            $lines[] = strrev($buffer);
        }

        $lines = array_reverse($lines);

        return "<pre>" . htmlspecialchars(implode("\n", $lines)) . "</pre>";
    });


    // Route::get('/offers-table', function () {

    //     $offers = Offer::with(['product', 'seller'])
    //         ->orderByDesc('id')
    //         ->take(10)
    //         ->get();

    //     $html = '
    //     <!DOCTYPE html>
    //     <html>
    //     <head>
    //         <title>Latest Offers</title>
    //         <style>
    //             body {
    //                 font-family: Arial, sans-serif;
    //                 padding: 30px;
    //                 background: #f7f7f7;
    //             }
    //             h2 {
    //                 text-align: center;
    //                 margin-bottom: 20px;
    //                 color: #333;
    //             }
    //             table {
    //                 border-collapse: collapse;
    //                 width: 100%;
    //                 background: white;
    //                 border-radius: 8px;
    //                 overflow: hidden;
    //                 box-shadow: 0 0 10px rgba(0,0,0,0.1);
    //             }
    //             th {
    //                 background: #4a90e2;
    //                 color: white;
    //                 padding: 12px;
    //                 text-align: left;
    //             }
    //             td {
    //                 padding: 12px;
    //                 border-bottom: 1px solid #ddd;
    //             }
    //             tr:hover {
    //                 background-color: #f1f1f1;
    //             }
    //             .badge {
    //                 padding: 4px 8px;
    //                 border-radius: 6px;
    //                 color: white;
    //                 font-size: 12px;
    //                 font-weight: bold;
    //             }
    //             .paid { background: #27ae60; }
    //             .unpaid { background: #c0392b; }
    //         </style>
    //     </head>
    //     <body>

    //     <h2>Latest 10 Offers</h2>

    //     <table>
    //         <thead>
    //             <tr>
    //                 <th>ID</th>
    //                 <th>Product</th>
    //                 <th>Seller</th>
    //                 <th>Price</th>
    //                 <th>Is Paid</th>
    //                 <th>Created At</th>
    //             </tr>
    //         </thead>

    //         <tbody>';

    //     foreach ($offers as $offer) {
    //         $html .= '
    //             <tr>
    //                 <td>'.$offer->id.'</td>
    //                 <td>'.($offer->product->slug ?? 'N/A').'</td>
    //                 <td>'.($offer->seller->username ?? 'N/A').'</td>
    //                 <td>'.number_format($offer->price, 2).'</td>
    //                 <td>' . ($offer->is_paid
    //                     ? '<span class="badge paid">PAID</span>'
    //                     : '<span class="badge unpaid">UNPAID</span>') . '</td>
    //                 <td>'.$offer->created_at->format('d M, Y h:i A').'</td>
    //             </tr>';
    //     }

    //     if ($offers->count() === 0) {
    //         $html .= '
    //             <tr>
    //                 <td colspan="6" style="text-align:center;">No Offers Found</td>
    //             </tr>';
    //     }

    //     $html .= '
    //         </tbody>
    //     </table>

    //     </body>
    //     </html>';

    //     return $html;
    // });


    Route::get('/fix-missing-shops', function () {

        $usersWithoutShop = User::doesntHave('shop')->get();

        $created = [];

        foreach ($usersWithoutShop as $user) {

            $shopName = $user->first_name . "'s Shop";
            $slug = Str::slug($shopName);

            if (Shop::where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(5);
            }

            $shop = Shop::create([
                'user_id'     => $user->id,
                'name'        => $shopName,
                'slug'        => $slug,
                'description' => 'Welcome to ' . $shopName . '!',
                'phone'       => null,
                'address'     => $user->billing_address,
                'settings'    => [],
            ]);

            $created[] = [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'created_shop' => $shopName
            ];
        }

        return response()->json([
            'total_fixed' => count($created),
            'details' => $created,
        ]);
    });


    Route::get('stripe/onboard/refresh', function (Request $request) {

        // 🔑 Get shop_id from query (sent by Stripe redirect)
        $shopId = $request->query('shop_id');

        if (!$shopId) {
            return view('stripe.failed');
        }

        $shop = Shop::find($shopId);

        if (!$shop || !$shop->stripe_account_id) {
            return view('stripe.failed');
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 🔁 Re-create onboarding link
        $accountLink = AccountLink::create([
            'account' => $shop->stripe_account_id,
            'refresh_url' => config('app.frontend_url') . '/stripe/onboard/refresh?shop_id=' . $shop->id,
            'return_url'  => config('app.frontend_url') . '/stripe/onboard/complete?shop_id=' . $shop->id,
            'type' => 'account_onboarding',
        ]);

        return redirect($accountLink->url);
    });

    Route::get('stripe/onboard/complete', function (Request $request) {

        $shopId = $request->query('shop_id');

        if (!$shopId) {
            return view('stripe.failed');
        }

        $shop = \App\Models\Shop::find($shopId);

        if (!$shop || !$shop->stripe_account_id) {
            return view('stripe.failed');
        }

        \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        $account = \Stripe\Account::retrieve($shop->stripe_account_id);

        return view('stripe.complete', [
            'connected' => (bool) $account->charges_enabled,
        ]);
    });


    Route::get('/run-release-hold', function () {
        if (request('key') !== 'test123') {
            abort(403);
        }
        Artisan::call('payments:release-hold');
        return nl2br(Artisan::output());
    });


    
    Route::get('/debug/payment-transfers', function () {

        $transfers = PaymentTransfer::with('shop')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'count' => $transfers->count(),
            'data' => $transfers->map(function ($t) {
                return [
                    'id' => $t->id,
                    'order_id' => $t->order_id,
                    'shop' => [
                        'id' => $t->shop_id,
                        'name' => optional($t->shop)->name,
                        'stripe_account_id' => optional($t->shop)->stripe_account_id,
                    ],
                    'amount_cents' => $t->amount_cents,
                    'platform_fee_cents' => $t->platform_fee_cents,
                    'net_to_shop_cents' => $t->amount_cents - $t->platform_fee_cents,
                    'currency' => strtoupper($t->currency),
                    'status' => $t->status,
                    'release_at' => optional($t->release_at)->toDateTimeString(),
                    'stripe_transfer_id' => $t->stripe_transfer_id,
                    'created_at' => $t->created_at->toDateTimeString(),
                ];
            }),
        ]);
    });

    
    Route::get('/debug/run-migrate', function () {

        // ✅ SAFETY CHECKS (VERY IMPORTANT)
        // abort_unless(
        //     app()->environment(['local', 'staging']) 
        //     || (auth()->check() && auth()->user()->is_admin),
        //     403
        // );

        Artisan::call('migrate', [
            '--force' => true, // required on production
        ]);

        return response()->json([
            'status' => 'success',
            'output' => Artisan::output(),
        ]);
    });




