<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Take-home auth stub.
 *
 * A real deployment would authenticate the request (Sanctum, session, etc.).
 * To keep this exercise focused on the feature rather than on auth plumbing,
 * every API request is resolved as the seeded demo user. Build user-scoped
 * work against $request->user() / auth()->user() exactly as you normally would.
 */
class ActAsDemoUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // The user is set on the guard rather than only on the request, so
        // auth()->user(), Auth::id() and the Gate see the demo user as well as
        // $request->user(). Setting a request user resolver alone is invisible
        // to the guard, which only reads the session.
        //
        // This is one query per request. HandleInertiaRequests already resolves
        // the user on every page to share it, so resolving it lazily saved nothing.
        //
        // An unseeded database has no user, which is the same shape as a
        // genuinely unauthenticated request (`php artisan migrate --seed` first).
        $demoUser = User::query()->orderBy('id')->first();

        if ($demoUser !== null) {
            Auth::setUser($demoUser);
        }

        return $next($request);
    }
}
