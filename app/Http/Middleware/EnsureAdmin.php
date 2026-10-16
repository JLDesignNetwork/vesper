<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Allow only users who pass the accessAdminPanel gate (Admin I until Phase 7 adds
     * the geolocation-free Admin II view). Everyone else is sent to their profile.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if (Auth::user()->cannot('accessAdminPanel')) {
            return redirect()->route('profile.show');
        }

        return $next($request);
    }
}
