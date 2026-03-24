<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function addUser()
    {
        return view('users/addUser');
    }

    public function editUser()
    {
        return view('users/editUser');
    }

    public function usersList(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');
        $status  = $request->get('status');

        $users = User::nonAdmin()
            ->with(['shops.products'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('shops', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== null, function ($q) use ($status) {
                $q->whereHas('shops', function ($sq) use ($status) {
                    $sq->where('vacation_mode', $status === 'active' ? 0 : 1);
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString(); // 🔥 keeps filters on pagination

        return view('users.usersList', compact('users'));
    }

    public function storeUserWithShop(Request $request)
    {
        $request->validate([
            // ================= USER =================
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'username'   => 'required|string|max:255|unique:users,username',
            'dob'        => 'required|date',
            'billing_address' => 'required|string',
            'password'   => 'required|string|min:6|confirmed',

            // optional user image
            'user_profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            // ================= SHOP =================
            'shop.name'    => 'required|string|max:255',
            'shop.phone'   => 'required|string|max:50',
            'shop.address' => 'required|string|max:512',
            'shop.description' => 'nullable|string',
            'shop.image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::transaction(function () use ($request) {

            /* ================= CREATE USER ================= */

            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
                'username',
                'dob',
                'billing_address',
            ]);

            $userData['password'] = Hash::make($request->password);
            $userData['role_id'] = 2; // Vendor
            $userData['slug'] = Str::slug($request->username);

            // user image
            if ($request->hasFile('user_profile_image')) {
                $file = $request->file('user_profile_image');
                $filename = 'user-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/users'), $filename);
                $userData['avatar'] = 'images/users/' . $filename;
            }

            $user = User::create($userData);

            /* ================= CREATE SHOP ================= */

            $shopData = $request->input('shop');
            $shopData['user_id'] = $user->id;
            $shopData['slug'] = Str::slug($shopData['name']);

            // shop image
            if ($request->hasFile('shop.image')) {
                $file = $request->file('shop.image');
                $filename = 'shop-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/shops'), $filename);
                $shopData['image'] = 'images/shops/' . $filename;
            }

            Shop::create($shopData);
        });

        return redirect()
            ->route('usersList')
            ->with('success', 'User and Shop created successfully');
    }

    public function edit($id)
    {
        $user = User::with('shop')->findOrFail($id);

        return view('users.editUser', compact('user'));
    }

    public function updateUserWithShop(Request $request, $id)
    {
        $user = User::with('shop')->findOrFail($id);
        $request->validate([
            // ================= USER =================
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'username'   => 'required|string|max:255|unique:users,username,' . $user->id,
            'dob'        => 'required|date',
            'billing_address' => 'required|string',
            'password'   => 'nullable|string|min:6|confirmed', // optional on update
            'user_profile_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            
            // ================= SHOP =================
            'shop.name'    => 'sometimes|string|max:255',
            'shop.phone'   => 'sometimes|string|max:50',
            'shop.address' => 'sometimes|string|max:512',
            'shop.description' => 'nullable|string',
            'shop.image'   => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        DB::transaction(function () use ($request, $user) {
            // ======== UPDATE USER ========
            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
                'username',
                'dob',
                'billing_address'
            ]);

            // ✅ ADD THIS
            if ($request->filled('trust_ap_id')) {
                $userData['trustap_user_id'] = $request->trust_ap_id;
            }

            if ($request->filled('password')) {

                $userData['password'] = $request->password;
            }

            // user image
            if ($request->hasFile('user_profile_image')) {
                $file = $request->file('user_profile_image');
                $filename = 'user-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/users'), $filename);
                $userData['avatar'] = 'images/users/' . $filename;
            }

            $user->update($userData);

            // ======== UPDATE SHOP ========
            $shopData = $request->input('shop');
            $shopData['slug'] = Str::slug($shopData['name']);
            // ✅ ADD THIS
            if ($request->filled('trust_ap_id')) {
                $shopData['trustap_user_id'] = $request->trust_ap_id;
            }

            // shop image
            if ($request->hasFile('shop.image')) {
                $file = $request->file('shop.image');
                $filename = 'shop-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/shops'), $filename);
                $shopData['image'] = 'images/shops/' . $filename;
            }

            if ($user->shop) {
                $user->shop->update($shopData);
            } else {
                $shopData['user_id'] = $user->id;
                Shop::create($shopData);
            }
        });

        return redirect()->route('usersList')->with('success', 'User and Shop updated successfully');
    }
    public function changePassword(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->password = $request->password; // cast will hash automatically
        $user->save();

        return back()->with('success', 'Password updated successfully');
    }


    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();

        return redirect()
            ->route('usersList')
            ->with('success', 'User and shop deleted successfully.');
    }

    public function viewProfile()
    {
        return view('users/viewProfile');
    }
}
