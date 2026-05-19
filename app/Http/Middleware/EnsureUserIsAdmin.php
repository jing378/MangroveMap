<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->isEndUser()) {
            return redirect()->route('dashboard');
        }

        if (Auth::check() && Auth::user()->isExpert()) {
            return redirect()->route('expert.dashboard');
        }

        return redirect()->route('login')->with('error', 'Admin access required.');
    }
}
