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
     * Confirm the callback answers a sign-in this browser started: the provider must be
     * configured and the state must match the single-use value issued at redirect.
     *
     * @throws \InvalidArgumentException
     */
    public function assertCallbackIsGenuine(string $provider, Request $request): void
    {
        if (! $this->isProviderConfigured($provider)) {
            throw new \InvalidArgumentException(__(':provider authentication is currently unavailable.', ['provider' => ucfirst($provider)]));
        }

        $issuedState = $request->session()->pull("oauth_state_{$provider}");
        $returnedState = $request->input('state');

        if (! is_string($issuedState) || ! is_string($returnedState) || ! hash_equals($issuedState, $returnedState)) {
            throw new \InvalidArgumentException(__('The sign-in request expired or did not come from this browser. Please try again.'));
        }
    }

    /**
     * Handle the provider callback and resolve user identity data.
     * Call assertCallbackIsGenuine() first.
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
                'email_verified' => filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => $data['name'] ?? $data['given_name'] ?? 'Google Member',
                'avatar' => $data['picture'] ?? null,
            ];
        }

        if ($provider === 'apple') {
            $code = $request->input('code');

            if (empty($code) || ! is_string($code)) {
                throw new \InvalidArgumentException(__('Apple authorization identity payload missing.'));
            }

            // Never trust an id_token supplied in the request: anyone can forge one.
            // Exchange the code with Apple and use only the id_token Apple returns over TLS.
            $tokenResponse = Http::asForm()->post('https://appleid.apple.com/auth/token', [
                'code' => $code,
                'client_id' => config('services.apple.client_id'),
                'client_secret' => config('services.apple.client_secret'),
                'redirect_uri' => url(config('services.apple.redirect', '/auth/apple/callback')),
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('id_token'))) {
                Log::error('Apple OAuth token exchange failed', ['status' => $tokenResponse->status()]);
                throw new \RuntimeException(__('Failed to exchange authorization code with Apple.'));
            }

            $claims = $this->decodeJwtClaims($tokenResponse->json('id_token'));

            if (($claims['aud'] ?? null) !== config('services.apple.client_id') || ($claims['iss'] ?? null) !== 'https://appleid.apple.com') {
                throw new \RuntimeException(__('Apple returned an identity token issued for a different application.'));
            }

            $userParam = json_decode($request->input('user', '{}'), true) ?: [];
            $nameParts = array_filter([$userParam['name']['firstName'] ?? '', $userParam['name']['lastName'] ?? '']);
            $fullName = implode(' ', $nameParts) ?: 'Apple Member';

            if (empty($claims['sub'])) {
                throw new \RuntimeException(__('Apple returned an identity token without a subject.'));
            }

            return [
                'provider_id' => (string) $claims['sub'],
                'email' => strtolower($claims['email'] ?? ''),
                'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'name' => $fullName,
                'avatar' => null,
            ];
        }

        throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
    }

    /**
     * Decode the claims of a JWT received directly from a provider's token endpoint.
     * The signature is not checked, so only pass tokens obtained server-to-server over TLS.
     *
     * @return array<string, mixed>
     */
    protected function decodeJwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) ? $claims : [];
    }

    /**
     * Find or provision the user for a resolved identity, marking their primary email
     * verified when the provider confirms that exact address.
     */
    public function linkOrAuthenticate(string $provider, array $identity, ?User $currentUser = null): User
    {
        $user = $this->findOrProvisionUser($provider, $identity, $currentUser);

        $providerConfirmedEmail = ! empty($identity['email_verified'])
            && ! empty($identity['email'])
            && strtolower($identity['email']) === strtolower((string) $user->email);

        if ($providerConfirmedEmail && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            app(RankService::class)->recomputeCreatorsOfChannelsJoinedBy($user);
        }

        return $user;
    }

    /**
     * Find or provision user from resolved identity.
     */
    protected function findOrProvisionUser(string $provider, array $identity, ?User $currentUser = null): User
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
