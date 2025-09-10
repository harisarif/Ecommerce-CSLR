<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


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
}
