<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserBrand;
use App\Models\UserSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            'shop',
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
            'shop' => $user->shop ? [
                'id' => $user->shop->id,
                'name' => $user->shop->name,
                'slug' => $user->shop->slug,
                'description' => $user->shop->description,
                'phone' => $user->shop->phone,
                'address' => $user->shop->address,
                'image' => $user->shop->image_url,
            ] : null,
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

        // 🔍 DEBUG: Log initial request data
        \Log::info('updateUserProfile - Request Data', [
            'user_id' => $user->id,
            'all_request_data' => $request->all(),
            'has_shop_data' => $request->has('shop'),
            'shop_data' => $request->input('shop'),
            'has_shop_image' => $request->hasFile('shop.image')
        ]);

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

            // ================= SHOP (support both nested and flat) =================
            'shop'             => 'sometimes|array',
            'shop.name'        => 'sometimes|string|max:255',
            'shop.phone'       => 'sometimes|string|max:50',
            'shop.address'     => 'sometimes|string|max:512',
            'shop.description' => 'nullable|string',
            'shop.image'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
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

        // ✅ Update shop if sent
        $hasShopNested = $request->has('shop');
        $hasShopFlat = $request->has('shop.name') || $request->has('shop.phone') || $request->has('shop.address');
        
        \Log::info('updateUserProfile - Shop Update Check', [
            'has_shop_nested' => $hasShopNested,
            'has_shop_flat' => $hasShopFlat,
            'user_has_shop' => $user->shop ? true : false,
            'user_shop_id' => $user->shop ? $user->shop->id : null
        ]);

        if ($hasShopNested || $hasShopFlat) {
            // Handle both nested and flat shop data structures
            if ($hasShopNested) {
                $shopData = $request->input('shop');
            } else {
                // Convert flat structure to nested
                $shopData = [
                    'name' => $request->input('shop.name'),
                    'phone' => $request->input('shop.phone'),
                    'address' => $request->input('shop.address'),
                    'description' => $request->input('shop.description'),
                ];
                // Remove null values
                $shopData = array_filter($shopData, function($value) {
                    return $value !== null;
                });
            }
            
            \Log::info('updateUserProfile - Shop Data Received', [
                'shop_data' => $shopData,
                'shop_data_keys' => array_keys($shopData ?? [])
            ]);
            
            // Generate slug if name is provided
            if (isset($shopData['name'])) {
                $slug = Str::slug($shopData['name']);
                
                \Log::info('updateUserProfile - Slug Generation', [
                    'original_name' => $shopData['name'],
                    'generated_slug' => $slug
                ]);
                
                // Make sure slug is unique
                $existingSlugCount = Shop::where('slug', $slug)->where('id', '!=', $user->shop->id ?? 0)->count();
                if ($existingSlugCount > 0) {
                    $slug .= '-' . Str::random(4);
                    \Log::info('updateUserProfile - Slug Modified for Uniqueness', [
                        'final_slug' => $slug,
                        'existing_count' => $existingSlugCount
                    ]);
                }
                
                $shopData['slug'] = $slug;
            }

            // Handle shop image upload
            if ($request->hasFile('shop.image')) {
                \Log::info('updateUserProfile - Shop Image Upload Started');
                $file = $request->file('shop.image');
                $filename = 'shop-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/shops'), $filename);
                $shopData['image'] = 'images/shops/' . $filename;
                \Log::info('updateUserProfile - Shop Image Uploaded', [
                    'filename' => $filename,
                    'image_path' => $shopData['image']
                ]);
            }

            \Log::info('updateUserProfile - Final Shop Data Before Update', [
                'final_shop_data' => $shopData,
                'user_has_existing_shop' => $user->shop ? true : false
            ]);

            // Update or create shop
            try {
                if ($user->shop) {
                    \Log::info('updateUserProfile - Updating Existing Shop', [
                        'shop_id' => $user->shop->id,
                        'update_data' => $shopData
                    ]);
                    $result = $user->shop->update($shopData);
                    \Log::info('updateUserProfile - Shop Update Result', ['result' => $result]);
                } else {
                    \Log::info('updateUserProfile - Creating New Shop', [
                        'user_id' => $user->id,
                        'create_data' => $shopData
                    ]);
                    $newShop = Shop::create($shopData);
                    \Log::info('updateUserProfile - New Shop Created', ['shop_id' => $newShop->id]);
                }
            } catch (\Exception $e) {
                \Log::error('updateUserProfile - Shop Update Failed', [
                    'error' => $e->getMessage(),
                    'shop_data' => $shopData
                ]);
            }
        }

        $user->load(['sizes.appCategory', 'sizes.size', 'brands.brand', 'shop']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user
        ]);
    }

    public function getActive()
    {
        $currency = Currency::active()->first();

        if (!$currency) {
            return response()->json([
                'status' => false,
                'message' => 'No active currency found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $currency,
        ]);
    }


}
