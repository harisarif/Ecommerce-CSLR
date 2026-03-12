<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminProfileController extends Controller
{

    public function viewProfile()
    {
        $admin = Auth::user();
        return view('admin.myprofile', compact('admin'));
    }


    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'first_name'        => 'required|string|max:255',
            'last_name'         => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email,' . $admin->id,
            'username'          => 'required|string|max:255|unique:users,username,' . $admin->id,
            'dob'               => 'nullable|date',
            'billing_address'   => 'nullable|string|max:500',
            'user_profile_image'=> 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only([
            'first_name',
            'last_name',
            'email',
            'username',
            'dob',
            'billing_address',
        ]);

        /** Image Upload */
         if ($request->hasFile('user_profile_image')) {
                $file = $request->file('user_profile_image');
                $filename = 'user-' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/users'), $filename);
                $data['avatar'] = 'images/users/' . $filename;
            }

        $admin->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request)
    {
        $admin = Auth::user();


          $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $admin->password = $request->password; // cast will hash automatically
        $admin->save();

        return back()->with('success', 'Password updated successfully');
    }

    /**
     * Reset admin password (force reset by admin himself)
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        Auth::user()->update([
            'password' => $request->password,
        ]);

        return back()->with('success', 'Password reset successfully.');
    }
}
