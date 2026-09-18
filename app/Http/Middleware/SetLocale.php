<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $supportedLocales = LanguageService::codes();

        $queryLang = $request->query('lang');

        if ($queryLang && in_array($queryLang, $supportedLocales, true)) {
            $locale = $queryLang;
            $request->session()->put('locale', $locale);
            if (Auth::check()) {
                Auth::user()->update(['preferred_locale' => $locale]);
            }
        } elseif (Auth::check()) {
            $user = Auth::user();
            // 1. User preferred language setting strictly overrides location
            if (! empty($user->preferred_locale) && in_array($user->preferred_locale, $supportedLocales, true)) {
                $locale = $user->preferred_locale;
                $request->session()->put('locale', $locale);
            } elseif ($request->session()->has('locale') && in_array($request->session()->get('locale'), $supportedLocales, true)) {
                $locale = $request->session()->get('locale');
            } else {
                // 2. Common language of registered location
                $locale = $user->resolveLocationLocale();
                $request->session()->put('locale', $locale);
            }
        } elseif ($request->session()->has('locale') && in_array($request->session()->get('locale'), $supportedLocales, true)) {
            $locale = $request->session()->get('locale');
        } else {
            $locale = config('app.locale', 'en');
        }

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
