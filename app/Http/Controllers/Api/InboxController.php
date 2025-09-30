<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    // list all offers where I'm buyer or seller
    public function index(Request $request)
    {
        $user = $request->user();

        $offers = Offer::with(['product','buyer','seller'])
            ->where(function($q) use ($user) {
                $q->where('buyer_id', $user->id)
                  ->orWhere('seller_id', $user->id);
            })
            ->orderByDesc('updated_at')
            ->paginate(30);

        return response()->json($offers);
    }
}
