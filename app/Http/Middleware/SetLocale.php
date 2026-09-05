<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['en', 'ru', 'fr', 'it'];

        $locale = $request->query('lang');

        if ($locale && in_array($locale, $supportedLocales, true)) {
            $request->session()->put('locale', $locale);
        } else {
            $locale = $request->session()->get('locale', config('app.locale', 'en'));
        }

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

