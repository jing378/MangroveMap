<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsExpert
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->isExpert()) {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if (Auth::check() && Auth::user()->isEndUser()) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('login')->with('error', 'Unauthorized access.');
    }
}
