<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => ['nullable', 'digits_between:1,11'],
            'password' => 'required|min:8|confirmed'
        ], [
            'email.unique' => 'This email address is already associated with an account. Please log in or use a different email.',
            'email.email' => 'Please enter a valid email address.',
            'email.required' => 'Email address is required.',
            'name.required' => 'Full name is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'phone.digits_between' => 'Contact number must contain only digits and be up to 11 digits long.',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->filled('password') && $request->filled('password_confirmation') && $request->input('password') !== $request->input('password_confirmation')) {
                if ($validator->errors()->has('password')) {
                    $validator->errors()->add('password_confirmation', $validator->errors()->first('password'));
                }
            }
        });

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'end_user',
            'phone' => $validated['phone'] ?? null,
        ]);

        Auth::login($user);

        try {
            event(new Registered($user));
        } catch (\Throwable $exception) {
            logger()->error('Failed to send registration verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            if ($request->accepts('application/json')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Account created, but verification email could not be sent. Please check your mail configuration or try again later.',
                    'email' => $user->email,
                ], 201);
            }

            return redirect('/login')->with('status', 'Account created, but verification email could not be sent. Please log in and resend verification.');
        }

        if ($request->accepts('application/json')) {
            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'email' => $user->email
            ], 201);
        }

        return redirect('/email/verify')->with('status', 'verification-link-sent');
    }
}
