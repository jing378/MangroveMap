<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class NewPasswordController extends Controller
{
    public function store(Request $request)
    {
        // Same password rules as sign-up, so a reset can't set a weaker password.
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => RegisteredUserController::passwordRules(),
        ], RegisteredUserController::passwordMessages());

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('password.reset', [
                'token' => $request->input('token'),
                'email' => $request->input('email'),
            ])->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }
}
