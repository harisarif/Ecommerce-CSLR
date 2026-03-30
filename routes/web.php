<?php

use App\Http\Controllers\AdminProfileController;
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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Hash;



    Route::prefix('/')->controller(AuthenticationController::class)->group(function () {
        Route::get('/login', 'signin')->name('login');
        Route::post('/login', 'login')->name('login.post');
        Route::post('/logout', 'logout')->name('logout');
    });

    Route::middleware(['auth'])->group(function () {

        Route::controller(DashboardController::class)->group(function () {
            Route::get('/', 'index')->name('dashboard');
        });




        Route::prefix('admin')->group(function () {
            Route::controller(AdminProfileController::class)->group(function () {
                Route::get('/profile-view', 'viewProfile')->name('viewProfile');
                Route::patch('/profile/update', 'updateProfile')->name('profile.update');
                Route::patch('/profile/change-password', 'changePassword')->name('profile.password');
            });
        });

        Route::prefix('admin')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });

        Route::controller(HomeController::class)->group(function () {
            Route::get('calendar','calendar')->name('calendar');
            Route::get('chatmessage','chatMessage')->name('chatMessage');
            Route::get('chatempty','chatempty')->name('chatempty');
            Route::get('email','email')->name('email');
            Route::get('error','error1')->name('error');
            Route::get('faq','faq')->name('faq');
            Route::get('gallery','gallery')->name('gallery');
            Route::get('kanban','kanban')->name('kanban');
            Route::get('pricing','pricing')->name('pricing');
            Route::get('termscondition','termsCondition')->name('termsCondition');
            Route::get('widgets','widgets')->name('widgets');
            Route::get('chatprofile','chatProfile')->name('chatProfile');
            Route::get('veiwdetails','veiwDetails')->name('veiwDetails');
            Route::get('blankPage','blankPage')->name('blankPage');
            Route::get('comingSoon','comingSoon')->name('comingSoon');
            Route::get('maintenance','maintenance')->name('maintenance');
            Route::get('starred','starred')->name('starred');
            Route::get('testimonials','testimonials')->name('testimonials');
        });


        // Users
        Route::prefix('users')->group(function () {
            Route::controller(UsersController::class)->group(function () {
                Route::get('/add-user', 'addUser')->name('addUser');
                Route::post('/store','storeUserWithShop')->name('store');
                Route::get('/users-grid', 'usersGrid')->name('usersGrid');
                Route::get('/users-list', 'usersList')->name('usersList');
                Route::get('/edit-user/{id}', 'edit')->name('editUser');
                Route::patch('/users/{id}','updateUserWithShop')->name('updateUserWithShop');
                Route::patch('/users/{id}/change-password', 'changePassword')->name('changePassword');
                Route::delete('/delete-user/{id}', 'deleteUser')->name('deleteUser');

            });
        });

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


    // Route::get('/fix-missing-shops', function () {

    //     $usersWithoutShop = User::doesntHave('shop')->get();

    //     $created = [];

    //     foreach ($usersWithoutShop as $user) {

    //         $shopName = $user->first_name . "'s Shop";
    //         $slug = Str::slug($shopName);

    //         if (Shop::where('slug', $slug)->exists()) {
    //             $slug .= '-' . Str::random(5);
    //         }

    //         $shop = Shop::create([
    //             'user_id'     => $user->id,
    //             'name'        => $shopName,
    //             'slug'        => $slug,
    //             'description' => 'Welcome to ' . $shopName . '!',
    //             'phone'       => null,
    //             'address'     => $user->billing_address,
    //             'settings'    => [],
    //         ]);

    //         $created[] = [
    //             'user_id' => $user->id,
    //             'shop_id' => $shop->id,
    //             'created_shop' => $shopName
    //         ];
    //     }

    //     return response()->json([
    //         'total_fixed' => count($created),
    //         'details' => $created,
    //     ]);
    // });


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

        $transfers = PaymentTransfer::with('shop')->orderByDesc('id')->limit(50)->get();

        return response()->make('
        <!DOCTYPE html>
        <html>
        <head>
            <title>Payment Transfers Debug</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; background: #f5f6fa; }
                h2 { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; background: #fff; }
                th, td { padding: 10px; border: 1px solid #ddd; font-size: 14px; text-align: left; }
                th { background: #2f3542; color: #fff; }
                tr:nth-child(even) { background: #f1f2f6; }
                .badge { padding: 4px 8px; border-radius: 4px; color: #fff; font-size: 12px; }
                .on_hold { background: orange; }
                .released { background: green; }
                pre { white-space: pre-wrap; word-wrap: break-word; max-width: 300px; }
            </style>
        </head>
        <body>
            <h2>Payment Transfers (Latest 50)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Shop</th>
                        <th>Stripe Account</th>
                        <th>Amount</th>
                        <th>Platform Fee</th>
                        <th>Net</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Release At</th>
                        <th>Stripe Transfer</th>
                        <th>Meta</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    ' .
                    $transfers->map(function($t) {
                        return '<tr>
                            <td>' . $t->id . '</td>
                            <td>#' . $t->order_id . '</td>
                            <td>' . optional($t->shop)->name . '</td>
                            <td>' . optional($t->shop)->stripe_account_id . '</td>
                            <td>' . number_format($t->amount_cents / 100, 2) . '</td>
                            <td>' . number_format($t->platform_fee_cents / 100, 2) . '</td>
                            <td>' . number_format(($t->amount_cents - $t->platform_fee_cents) / 100, 2) . '</td>
                            <td>' . strtoupper($t->currency) . '</td>
                            <td><span class="badge ' . $t->status . '">' . strtoupper($t->status) . '</span></td>
                            <td>' . optional($t->release_at)->toDateTimeString() . '</td>
                            <td>' . $t->stripe_transfer_id . '</td>
                            <td><pre>' . json_encode($t->meta, JSON_PRETTY_PRINT) . '</pre></td>
                            <td>' . $t->created_at->toDateTimeString() . '</td>
                        </tr>';
                    })->implode('') .
                '
                </tbody>
            </table>
        </body>
        </html>
        ', 200, ['Content-Type' => 'text/html']);
    });

    // DELETE PaymentTransfer by ID
    Route::delete('/debug/payment-transfer/{id}', function ($id) {
        $paymentTransfer = PaymentTransfer::find($id);

        if (!$paymentTransfer) {
            return response()->json([
                'status' => 'error',
                'message' => 'PaymentTransfer not found'
            ], 404);
        }

        $paymentTransfer->delete();

        return response()->json([
            'status' => 'success',
            'message' => "PaymentTransfer with ID $id deleted successfully"
        ]);
    })->name('payment-transfer.delete');


    Route::get('/debug/payment-transfers-html', function () {

        $transfers = PaymentTransfer::with(['shop', 'order'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->make('
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Payment Transfers Debug</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">

            <!-- Bootstrap 5 CDN -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

            <style>
                body { background: #f8f9fa; }
                table { font-size: 13px; }
                th { white-space: nowrap; }
                td { vertical-align: middle; }
                .badge { font-size: 12px; }
                .code { font-family: monospace; font-size: 12px; }
                .muted { color: #6c757d; font-size: 12px; }
            </style>
        </head>
        <body>

        <div class="container-fluid py-4">
            <h3 class="mb-3">💳 Payment Transfers Debug</h3>
            <p class="text-muted">Showing latest 50 records</p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Order</th>
                            <th>Shop</th>
                            <th>Stripe Account</th>

                            <th>Payment Intent</th>
                            <th>Charge</th>
                            <th>Transfer</th>

                            <!-- OLD (LEGACY) -->
                            <th>Amount (Legacy)</th>
                            <th>Platform Fee (Legacy)</th>
                            <th>Net (Legacy)</th>
                            <th>Currency</th>

                            <!-- NEW (STRIPE REAL) -->
                            <th>Checkout Amount</th>
                            <th>Checkout Currency</th>

                            <th>Gross (Stripe)</th>
                            <th>Stripe Fee</th>
                            <th>Net (Stripe)</th>
                            <th>Settlement Currency</th>
                            <th>FX Rate</th>

                            <th>Status</th>
                            <th>Release At</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    ' . $transfers->map(function ($t) {

                        $statusBadge = match ($t->status) {
                            "on_hold" => "warning",
                            "released" => "success",
                            "failed" => "danger",
                            default => "secondary",
                        };

                        $legacyNet = ($t->amount_cents ?? 0) - ($t->platform_fee_cents ?? 0);

                        return '
                        <tr id="transfer-row-' . $t->id . '">
                            <td>#' . $t->id . '</td>
                            <td>' . ($t->order_id ?? '-') . '</td>
                            <td>' . (optional($t->shop)->name ?? '-') . '</td>
                            <td class="code">' . (optional($t->shop)->stripe_account_id ?? '-') . '</td>

                            <td class="code">' . ($t->payment_intent_id ?? '-') . '</td>
                            <td class="code">' . ($t->charge_id ?? '-') . '</td>
                            <td class="code">' . ($t->stripe_transfer_id ?? '-') . '</td>

                            <!-- LEGACY -->
                            <td>' . number_format(($t->amount_cents ?? 0) / 100, 2) . '</td>
                            <td>' . number_format(($t->platform_fee_cents ?? 0) / 100, 2) . '</td>
                            <td><strong>' . number_format($legacyNet / 100, 2) . '</strong></td>
                            <td>' . strtoupper($t->currency ?? '-') . '</td>

                            <!-- STRIPE -->
                            <td>' . number_format(($t->checkout_amount_cents ?? 0) / 100, 2) . '</td>
                            <td>' . strtoupper($t->checkout_currency ?? '-') . '</td>

                            <td>' . number_format(($t->gross_amount_cents ?? 0) / 100, 2) . '</td>
                            <td>' . number_format(($t->stripe_fee_cents ?? 0) / 100, 2) . '</td>
                            <td><strong>' . number_format(($t->net_amount_cents ?? 0) / 100, 2) . '</strong></td>
                            <td>' . strtoupper($t->settlement_currency ?? '-') . '</td>
                            <td>' . ($t->exchange_rate ? number_format($t->exchange_rate, 6) : '-') . '</td>

                            <td>
                                <span class="badge bg-' . $statusBadge . '">
                                    ' . strtoupper($t->status) . '
                                </span>
                            </td>

                            <td>' . optional($t->release_at)->toDateTimeString() . '</td>
                            <td>' . optional($t->created_at)->toDateTimeString() . '</td>
                                                <td>
                                <button class="btn btn-sm btn-danger" onclick="deleteTransfer(' . $t->id . ')">Delete</button>
                            </td>
                        </tr>';

                    })->implode('') . '
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            function deleteTransfer(id) {
                if (!confirm("Are you sure you want to delete PaymentTransfer #" + id + "?")) return;

                fetch("/debug/payment-transfer/" + id, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": "' . csrf_token() . '",
                        "Accept": "application/json"
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        const row = document.getElementById("transfer-row-" + id);
                        if (row) row.remove();
                        alert(data.message);
                    } else {
                        alert(data.message || "Failed to delete PaymentTransfer");
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("An error occurred");
                });
            }
            </script>

        </body>
        </html>
        ');
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


    Route::get('/delete-user-by-email/{email}', function ($email) {

        $user = User::where('email', $email)->first();

            if (! $user) {
                return response('User not found', 404);
            }

            $user->delete(); // 🔥 shops auto-deleted via model event

            return response('User and shops deleted successfully');
    });












    /*
|--------------------------------------------------------------------------
| Dynamic Fields
|--------------------------------------------------------------------------
*/
function userFields() {
    $model = new User();

    $fillable = $model->getFillable();
    $hidden = $model->getHidden();

    return array_values(array_diff($fillable, $hidden));
}

/*
|--------------------------------------------------------------------------
| LIST USERS
|--------------------------------------------------------------------------
*/
Route::get('/users', function () {

    $users = User::latest()->get();
    $fields = userFields();

    $html = "<h2>Users</h2>";
    $html .= "<a href='/users/create'>+ Create User</a><br><br>";

    $html .= "<table border='1' cellpadding='8'><tr>";

    foreach ($fields as $field) {
        $html .= "<th>$field</th>";
    }
    $html .= "<th>Actions</th></tr>";

    foreach ($users as $user) {
        $html .= "<tr>";
        foreach ($fields as $field) {
            $value = $user->$field ?? '';
            $html .= "<td>" . htmlspecialchars($value) . "</td>";
        }

        $html .= "<td>
            <a href='/users/{$user->id}/edit'>Edit</a>
            <form method='POST' action='/users/{$user->id}' style='display:inline;'>
                ".csrf_field().method_field('DELETE')."
                <button>Delete</button>
            </form>
        </td>";

        $html .= "</tr>";
    }

    $html .= "</table>";

    return $html;
});


/*
|--------------------------------------------------------------------------
| CREATE FORM
|--------------------------------------------------------------------------
*/
Route::get('/users/create', function () {

    $fields = userFields();

    $html = "<h2>Create User</h2>";
    $html .= "<form method='POST' action='/users'>";
    $html .= csrf_field();

    foreach ($fields as $field) {

        $type = 'text';

        if (str_contains($field, 'email')) $type = 'email';
        if (str_contains($field, 'password')) $type = 'password';
        if (str_contains($field, 'date')) $type = 'date';

        $html .= "<label>$field</label><br>";
        $html .= "<input type='$type' name='$field'><br><br>";
    }

    $html .= "<button>Create</button></form>";
    $html .= "<br><a href='/users'>Back</a>";

    return $html;
});


/*
|--------------------------------------------------------------------------
| STORE USER
|--------------------------------------------------------------------------
*/
Route::post('/users', function (Request $request) {

    $data = $request->only(userFields());

    if (isset($data['password'])) {
        $data['password'] = Hash::make($data['password']);
    }

    User::create($data);

    return redirect('/users');
});


/*
|--------------------------------------------------------------------------
| EDIT FORM
|--------------------------------------------------------------------------
*/
Route::get('/users/{id}/edit', function ($id) {

    $user = User::findOrFail($id);
    $fields = userFields();

    $html = "<h2>Edit User</h2>";
    $html .= "<form method='POST' action='/users/{$user->id}'>";
    $html .= csrf_field().method_field('PUT');

    foreach ($fields as $field) {

        $type = 'text';

        if (str_contains($field, 'email')) $type = 'email';
        if ($field == 'password') continue; // skip password edit here

        $value = htmlspecialchars($user->$field ?? '');

        $html .= "<label>$field</label><br>";
        $html .= "<input type='$type' name='$field' value='$value'><br><br>";
    }

    $html .= "<button>Update</button></form>";
    $html .= "<br><a href='/users'>Back</a>";

    return $html;
});


/*
|--------------------------------------------------------------------------
| UPDATE USER
|--------------------------------------------------------------------------
*/
Route::put('/users/{id}', function (Request $request, $id) {

    $user = User::findOrFail($id);
    $data = $request->only(userFields());

    $user->update($data);

    return redirect('/users');
});


/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/
Route::delete('/users/{id}', function ($id) {
    User::findOrFail($id)->delete();
    return redirect('/users');
});
