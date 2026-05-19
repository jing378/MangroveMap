<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class AuthenticatedSessionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($validated)) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user instanceof \App\Models\User && ! $user->hasVerifiedEmail()) {
                return redirect()->intended(route('verification.notice'));
            }

            return redirect()->intended(Auth::user()->homeRoute());
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
            'password' => 'Invalid credentials'
        ]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
