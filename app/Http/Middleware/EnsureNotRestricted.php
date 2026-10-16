<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a member read-only while a ban vote about them is open (audit finding AU-6).
 * They can still read and leave channels; actions behind this middleware are refused.
 * The member is told only that an admin review is pending (owner decision A3).
 */
class EnsureNotRestricted
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isRestricted()) {
            return $next($request);
        }

        $message = __('Your account is restricted pending an admin review.');

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return back()->withErrors(['restricted' => $message]);
    }
}
