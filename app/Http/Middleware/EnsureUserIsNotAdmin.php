<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resident/expert tool pages (Delineate, Drone Upload, Tile Explorer, the drone review editor): admins don't
 * draw, upload or review there, so opening one by its address sends them back to the admin pages.
 */
class EnsureUserIsNotAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'That page is for residents and experts. Admins manage delineations and drone surveys under Map Layers.');
        }

        return $next($request);
    }
}
