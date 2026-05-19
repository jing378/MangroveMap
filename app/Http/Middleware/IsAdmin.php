<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->isEndUser()) {
            return redirect()->route('dashboard')->with('error', 'Admin access required');
        }

        if (Auth::check() && Auth::user()->isExpert()) {
            return redirect()->route('expert.dashboard')->with('error', 'Admin access required');
        }

        return redirect('/')->with('error', 'Unauthorized access');
    }
}