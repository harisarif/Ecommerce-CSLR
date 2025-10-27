<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBrand;
use App\Models\UserSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;


class UserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string|min:4',
            'password' => 'required|string|min:4|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Old password is incorrect',
            ], 401);
        }
        $user->password = bcrypt($request->password);
        $user->save();
        return response()->json([
            'message' => 'Password changed successfully',
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'sometimes|string|max:50',
            'about_me' => 'nullable|string|max:1000',
            'country_id' => 'nullable|integer|exists:location_countries,id',
            'state_id' => 'nullable|integer|exists:location_states,id',
            'city_id' => 'nullable|integer|exists:location_cities,id',
            'address' => 'nullable|string|max:500',
            'zip_code' => 'nullable|string|max:20',
            'show_email' => 'sometimes|boolean',
            'show_phone' => 'sometimes|boolean',
            'show_location' => 'sometimes|boolean',
        ]);

        // Update only the fields that are present in the request
        $updatableFields = [
            'first_name', 'last_name', 'email', 'phone_number', 'about_me',
            'country_id', 'state_id', 'city_id', 'address', 'zip_code',
            'show_email', 'show_phone', 'show_location'
        ];

        foreach ($updatableFields as $field) {
            if ($request->has($field)) {
                $user->$field = $request->input($field);
            }
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255'
        ]);

        $username = $request->username;

        // Check if username exists
        $exists = User::where('username', $username)->exists();

        if (!$exists) {
            return response()->json([
                'available' => true,
                'message'   => 'Username is available',
                'username'  => $username
            ]);
        }

        // Generate 3 suggestions
        $suggestions = [];
        for ($i = 0; $i < 3; $i++) {
            $suggestions[] = $username . rand(10, 9999);
        }

        return response()->json([
            'available'    => false,
            'message'      => 'Username already taken',
            'suggestions'  => $suggestions
        ]);
    }



    public function getUserProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // Eager load all relations
        $user->load([
            // 'shop',
            'sizes.appCategory',
            'sizes.size',
            'brands.brand'
        ]);

        // 🧠 Transform sizes data
        $sizes = $user->sizes->map(function ($item) {
            return [
                'category_id' => $item->app_category_id,
                'category_name' => $item->appCategory->title_meta_tag ?? null,
                'size_id' => $item->size_id,
                'size_name' => $item->size->name ?? null,
            ];
        });

        // 🧠 Transform brands data
        $brands = $user->brands->map(function ($item) {
            return [
                'brand_id' => $item->brand_id,
                'brand_name' => $item->brand->name ?? null,
                'brand_image' => $item->brand->image_path ? url($item->brand->image_path) : null,
            ];
        });

        // 🧠 Build final clean response
        $profileData = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'dob' => $user->dob,
            'billing_address' => $user->billing_address,
            // 'shop' => $user->shop ? [
            //     'id' => $user->shop->id,
            //     'name' => $user->shop->name,
            //     'slug' => $user->shop->slug,
            //     'description' => $user->shop->description,
            // ] : null,
            'sizes' => $sizes,
            'brands' => $brands,
        ];

        return response()->json([
            'success' => true,
            'data' => $profileData,
        ]);
    }


    public function updateUserProfile(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        // ✅ Conditional validation
        $validated = $request->validate([
            'first_name'      => 'sometimes|required|string|max:255',
            'last_name'       => 'sometimes|required|string|max:255',
            'email'           => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'dob'             => 'sometimes|required|date',
            'username'        => 'sometimes|required|string|max:255|unique:users,username,' . $user->id,
            'billing_address' => 'sometimes|required|string',

            'sizes'                    => 'sometimes|array',
            'sizes.*.category_id'      => 'required_with:sizes|exists:categories,id',
            'sizes.*.size_id'          => 'required_with:sizes|exists:sizes,id',

            'brands'                   => 'sometimes|array',
            'brands.*'                 => 'required|exists:brands,id',
        ]);

        // ✅ Update only provided fields
        $updateData = collect($request->only([
            'first_name',
            'last_name',
            'email',
            'dob',
            'username',
            'billing_address',
        ]))->filter()->toArray();

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // ✅ Update sizes if sent
        if ($request->has('sizes')) {
            UserSize::where('user_id', $user->id)->delete();
            foreach ($request->sizes as $sizeData) {
                UserSize::create([
                    'user_id'         => $user->id,
                    'app_category_id' => $sizeData['category_id'],
                    'size_id'         => $sizeData['size_id'],
                ]);
            }
        }

        // ✅ Update brands if sent
        if ($request->has('brands')) {
            UserBrand::where('user_id', $user->id)->delete();
            foreach ($request->brands as $brandId) {
                UserBrand::create([
                    'user_id'  => $user->id,
                    'brand_id' => $brandId,
                ]);
            }
        }

        $user->load(['sizes.appCategory', 'sizes.size', 'brands.brand']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user
        ]);
    }


}
