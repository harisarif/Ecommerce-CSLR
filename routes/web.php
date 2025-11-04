<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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
