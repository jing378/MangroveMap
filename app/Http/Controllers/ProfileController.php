<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Show the profile page
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        return view('components.profile', compact('user'));
    }

    /**
     * Update the profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'regex:/^[0-9]{11}$/', 'string'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];

        // Only require organization for admin users
        if ($user->role === 'admin') {
            $rules['organization'] = ['nullable', 'string', 'max:255'];
        }

        $messages = [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use. Please use a different email.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be exactly 11 digits (e.g., 09123456789).',
        ];

        $validated = $request->validate($rules, $messages);

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $path = $request->file('profile_image')->store('profiles', 'public');
            $validated['profile_image'] = $path;
        }

        // Update user profile - only update fields that are in the validated data
        $fieldsToUpdate = ['name', 'email', 'phone', 'profile_image'];
        if ($user->role === 'admin') {
            $fieldsToUpdate[] = 'organization';
        }

        foreach ($fieldsToUpdate as $field) {
            if (isset($validated[$field])) {
                $user->{$field} = $validated[$field];
            }
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Update the user's password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Check if current password is correct
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Update password
        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Password updated successfully!');
    }
}
