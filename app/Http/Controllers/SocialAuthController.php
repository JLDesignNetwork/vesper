<?php

namespace App\Http\Controllers;

use App\Services\OAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialAuthController extends Controller
{
    public function __construct(
        protected OAuthService $oauthService
    ) {}

    /**
     * Redirect operative to the external OAuth provider.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (! in_array($provider, ['google', 'apple'], true)) {
            return redirect()->route('login')->withErrors(['login' => __('Unsupported authentication provider.')]);
        }

        if (! $this->oauthService->isProviderConfigured($provider)) {
            // If in local development and not configured, provide clear instruction
            if (app()->environment('local')) {
                return redirect()->route('login')->withErrors([
                    'login' => __(":provider credentials not configured in .env. Please set :id and :secret.", [
                        'provider' => ucfirst($provider),
                        'id' => strtoupper($provider) . '_CLIENT_ID',
                        'secret' => strtoupper($provider) . '_CLIENT_SECRET',
                    ]),
                ]);
            }

            return redirect()->route('login')->withErrors(['login' => __(':provider authentication is currently unavailable.', ['provider' => ucfirst($provider)])]);
        }

        return redirect()->away($this->oauthService->getAuthUrl($provider));
    }

    /**
     * Handle the incoming OAuth callback from the provider.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (! in_array($provider, ['google', 'apple'], true)) {
            return redirect()->route('login')->withErrors(['login' => __('Unsupported authentication provider.')]);
        }

        try {
            $identity = $this->oauthService->resolveUserFromCallback($provider, $request);
            $user = $this->oauthService->linkOrAuthenticate($provider, $identity, Auth::user());

            // If user was already authenticated, they linked their account
            if (Auth::check()) {
                return back()->with('status', __(':provider account linked successfully.', ['provider' => ucfirst($provider)]));
            }

            // If operative has 2FA enabled, intercept and challenge
            if ($user->hasTwoFactor()) {
                session(['2fa_user_id' => $user->id, '2fa_remember' => true]);

                return redirect()->route('2fa.challenge');
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended($user->homeRoute());
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors([
                'login' => __('Authentication with :provider failed: :error', [
                    'provider' => ucfirst($provider),
                    'error' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
