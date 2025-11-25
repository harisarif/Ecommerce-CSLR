<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Offer;


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


Route::get('/offers-table', function () {

    $offers = Offer::with(['product', 'seller'])
        ->orderByDesc('id')
        ->take(10)
        ->get();

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Latest Offers</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                padding: 30px;
                background: #f7f7f7;
            }
            h2 {
                text-align: center;
                margin-bottom: 20px;
                color: #333;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            th {
                background: #4a90e2;
                color: white;
                padding: 12px;
                text-align: left;
            }
            td {
                padding: 12px;
                border-bottom: 1px solid #ddd;
            }
            tr:hover {
                background-color: #f1f1f1;
            }
            .badge {
                padding: 4px 8px;
                border-radius: 6px;
                color: white;
                font-size: 12px;
                font-weight: bold;
            }
            .paid { background: #27ae60; }
            .unpaid { background: #c0392b; }
        </style>
    </head>
    <body>

    <h2>Latest 10 Offers</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Seller</th>
                <th>Price</th>
                <th>Is Paid</th>
                <th>Created At</th>
            </tr>
        </thead>

        <tbody>';

    foreach ($offers as $offer) {
        $html .= '
            <tr>
                <td>'.$offer->id.'</td>
                <td>'.($offer->product->slug ?? 'N/A').'</td>
                <td>'.($offer->seller->name ?? 'N/A').'</td>
                <td>'.number_format($offer->price, 2).'</td>
                <td>' . ($offer->is_paid
                    ? '<span class="badge paid">PAID</span>'
                    : '<span class="badge unpaid">UNPAID</span>') . '</td>
                <td>'.$offer->created_at->format('d M, Y h:i A').'</td>
            </tr>';
    }

    if ($offers->count() === 0) {
        $html .= '
            <tr>
                <td colspan="6" style="text-align:center;">No Offers Found</td>
            </tr>';
    }

    $html .= '
        </tbody>
    </table>

    </body>
    </html>';

    return $html;
});
