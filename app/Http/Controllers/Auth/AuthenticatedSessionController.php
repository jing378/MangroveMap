<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    /** Wrong passwords allowed per email and IP before a short lockout. */
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // At most 5 wrong passwords a minute for the same email from the same IP (the route also limits
        // each IP across all emails: throttle:login). Stops password guessing without locking out the
        // real owner on another network.
        $throttleKey = 'login|'.Str::lower($validated['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withInput($request->only('email', 'remember'))->withErrors([
                'email' => "Too many login attempts. Please wait {$seconds} seconds and try again.",
            ]);
        }

        if (Auth::attempt($validated, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user instanceof User) {
                $user->update(['last_login_at' => now()]);
                UserActivity::record($user, 'Logged in', 'Authentication', 'success');
            }

            if ($user instanceof User && ! $user->hasVerifiedEmail()) {
                return redirect()->intended(route('verification.notice'));
            }

            return redirect()->intended(Auth::user()->homeRoute());
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        return back()->withErrors([
            'email' => 'Invalid credentials',
            'password' => 'Invalid credentials',
        ]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->boolean('to_login')) {
            return redirect()->route('login');
        }

        return redirect('/');
    }
}
