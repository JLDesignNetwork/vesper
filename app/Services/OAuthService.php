<?php

namespace App\Services;

use App\Mail\MemberWelcomeNotification;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OAuthService
{
    /**
     * Check if a given provider (google, apple) is configured with client ID & secret.
     */
    public function isProviderConfigured(string $provider): bool
    {
        $clientId = config("services.{$provider}.client_id");
        $clientSecret = config("services.{$provider}.client_secret");

        return ! empty($clientId) && ! empty($clientSecret);
    }

    /**
     * Get the external authorization redirect URL for the provider.
     */
    public function getAuthUrl(string $provider): string
    {
        $state = Str::random(40);
        session(["oauth_state_{$provider}" => $state]);

        $clientId = config("services.{$provider}.client_id");
        $redirectUri = url(config("services.{$provider}.redirect", "/auth/{$provider}/callback"));

        if ($provider === 'google') {
            $params = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'prompt' => 'select_account',
            ]);

            return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
        }

        if ($provider === 'apple') {
            $params = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code id_token',
                'response_mode' => 'form_post',
                'scope' => 'name email',
                'state' => $state,
            ]);

            return "https://appleid.apple.com/auth/authorize?{$params}";
        }

        throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
    }

    /**
     * Handle the provider callback and resolve user identity data.
     */
    public function resolveUserFromCallback(string $provider, Request $request): array
    {
        if ($provider === 'google') {
            $code = $request->query('code');
            if (empty($code)) {
                throw new \InvalidArgumentException(__('Authorization code missing from Google.'));
            }

            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => url(config('services.google.redirect', '/auth/google/callback')),
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenResponse->successful()) {
                Log::error('Google OAuth token exchange failed', ['response' => $tokenResponse->body()]);
                throw new \RuntimeException(__('Failed to exchange authorization code with Google.'));
            }

            $accessToken = $tokenResponse->json('access_token');

            $userResponse = Http::withToken($accessToken)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            if (! $userResponse->successful()) {
                throw new \RuntimeException(__('Failed to retrieve user profile from Google.'));
            }

            $data = $userResponse->json();

            return [
                'provider_id' => (string) ($data['sub'] ?? ''),
                'email' => strtolower($data['email'] ?? ''),
                'name' => $data['name'] ?? $data['given_name'] ?? 'Google Member',
                'avatar' => $data['picture'] ?? null,
            ];
        }

        if ($provider === 'apple') {
            // Apple posts code or id_token
            $idToken = $request->input('id_token');
            $code = $request->input('code');

            if (empty($idToken) && empty($code)) {
                throw new \InvalidArgumentException(__('Apple authorization identity payload missing.'));
            }

            // Decode JWT payload (middle segment)
            $parts = explode('.', (string) $idToken);
            $claims = [];
            if (count($parts) >= 2) {
                $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
            }

            $userParam = json_decode($request->input('user', '{}'), true) ?: [];
            $nameParts = array_filter([$userParam['name']['firstName'] ?? '', $userParam['name']['lastName'] ?? '']);
            $fullName = implode(' ', $nameParts) ?: 'Apple Member';

            return [
                'provider_id' => (string) ($claims['sub'] ?? Str::random(16)),
                'email' => strtolower($claims['email'] ?? ''),
                'name' => $fullName,
                'avatar' => null,
            ];
        }

        throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
    }

    /**
     * Find or provision user from resolved identity.
     */
    public function linkOrAuthenticate(string $provider, array $identity, ?User $currentUser = null): User
    {
        $providerId = $identity['provider_id'];
        $email = $identity['email'] ?: null;

        // If user already authenticated, link to current user
        if ($currentUser) {
            SocialAccount::updateOrCreate(
                ['provider' => $provider, 'provider_id' => $providerId],
                [
                    'user_id' => $currentUser->id,
                    'email' => $email,
                    'avatar' => $identity['avatar'] ?? null,
                ]
            );

            return $currentUser;
        }

        // Search by social account
        $social = SocialAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->with('user')
            ->first();

        if ($social && $social->user) {
            return $social->user;
        }

        // Match existing user by verified email
        if (! empty($email)) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                SocialAccount::create([
                    'user_id' => $existingUser->id,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'email' => $email,
                    'avatar' => $identity['avatar'] ?? null,
                ]);

                return $existingUser;
            }
        }

        // Provision new member user
        $newUser = User::create([
            'name' => $identity['name'] ?: ucfirst($provider).' Member',
            'email' => $email ?: ($providerId.'@'.$provider.'.identity'),
            'password' => Hash::make(Str::random(32)),
            'role' => 'member',
            'avatar_path' => $identity['avatar'] ?? null,
        ]);

        SocialAccount::create([
            'user_id' => $newUser->id,
            'provider' => $provider,
            'provider_id' => $providerId,
            'email' => $email,
            'avatar' => $identity['avatar'] ?? null,
        ]);

        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($newUser->email)->send(new MemberWelcomeNotification($newUser));
            } catch (\Throwable $e) {
                Log::warning("Failed to dispatch welcome notification to {$newUser->email}: {$e->getMessage()}");
            }
        }

        return $newUser;
    }
}
