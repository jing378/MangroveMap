<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers when each signed-in user was last on the site (users.last_seen_at), for "Last seen"
 * on the admin pages. Written at most once a minute per user. Unlike last_login_at it also counts
 * users kept signed in by "remember me".
 */
class TrackUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before the page is built, so a page that lists activity already counts this visit.
        $user = $request->user();
        if ($user instanceof User) {
            $user->markSeen();
        }

        return $next($request);
    }
}
