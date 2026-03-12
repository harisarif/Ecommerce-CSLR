<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrustapService;
use Illuminate\Http\Request;


class SellerController extends Controller
{
   protected $trustap;

    public function __construct(TrustapService $trustap)
    {
        $this->trustap = $trustap;
    }
    public function addTracking(Request $request, $orderId)
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'carrier' => 'required|string'
        ]);

        $order = Order::findOrFail($orderId);

        $seller = auth()->user(); // seller user

        $this->trustap->addTracking(
            $order->trustap_transaction_id,
            $seller->trustap_user_id,
            $request->carrier,
            $request->tracking_number
        );

        $order->update([
            'tracking_number' => $request->tracking_number,
            'trustap_status' => 'shipped'
        ]);

        return response()->json([
            'message' => 'Tracking added successfully'
        ]);
    }
    
}
