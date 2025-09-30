<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    // create or update shop for logged-in user
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable','string','max:255', Rule::unique('shops','slug')->ignore(optional($user->shop)->id)],
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:512',
            'settings' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048', // ✅ new rule
        ]);

        // handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // make sure folder exists
            $destination = public_path('images/shops');
            if (!file_exists($destination)) {
                mkdir($destination, 0777, true);
            }

            $filename = Str::slug($data['name'] ?? $user->username) . '-' . time() . '.' . $file->getClientOriginalExtension();

            // move file
            $file->move($destination, $filename);

            // save relative path into DB
            $data['image'] = 'images/shops/' . $filename;
        }


        $shop = Shop::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['user_id' => $user->id])
        );

        return response()->json([
            'message' => 'Shop saved',
            'data' => $shop->fresh()
        ], 201);
    }

    // get my shop (logged in)
    public function myShop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop()->with('products')->first();

        if (!$shop) {
            return response()->json(['message' => 'No shop found for this user'], 404);
        }

        return response()->json(['data' => $shop]);
    }

    // public shop page by id (or slug)
    public function show($id)
    {
        // allow numeric id or slug
        $shop = null;
        if (is_numeric($id)) {
            $shop = Shop::with('products')->find($id);
        } else {
            $shop = Shop::with('products')->where('slug', $id)->first();
        }

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        return response()->json(['data' => $shop]);
    }
}
