<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class WishListController extends Controller
{
    public function getWhishlistProductsByUser(){
        $product = Wishlist::with(['product.details', 'product.licenseKeys', 'product.searchIndexes', 'product.appCategory', 'product.user', 'product.variations', 'product.defaultVariationOptions',])
        ->where('user_id', Auth::user()->id)
        ->paginate(10);
        return response()->json($product);
    }

    public function addToWishlist($product_id){
        if(Wishlist::isInWishlist(Auth::user()->id, $product_id)){
            return response()->json(['message' => 'Product already in wishlist']);
        }
        $wishlist = new Wishlist();
        $wishlist->user_id = Auth::user()->id;
        $wishlist->product_id = $product_id;
        $wishlist->save();
        return response()->json($wishlist);
    }

    public function removeFromWishlist($product_id){
        $wishlist = Wishlist::where('user_id', Auth::user()->id)
        ->where('product_id', $product_id)
        ->first();
        $wishlist->delete();
        return response()->json($wishlist);
    }
}
