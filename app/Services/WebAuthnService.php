<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebAuthnService
{
    /**
     * Generate registration options challenge for an authenticated operative.
     */
    public function generateRegisterOptions(User $user): array
    {
        $challenge = Str::random(32);
        session(['webauthn_register_challenge' => $challenge]);

        $host = request()->getHost();

        // Existing credentials to exclude from re-registering
        $existing = $user->webauthnCredentials->map(fn ($c) => [
            'type' => 'public-key',
            'id' => $c->credential_id,
        ])->all();

        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => config('app.name', 'Vesper'),
                'id' => $host,
            ],
            'user' => [
                'id' => base64_encode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256 (ECDSA P-256)
                ['type' => 'public-key', 'alg' => -257], // RS256 (RSA SHA-256)
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred',
                'residentKey' => 'preferred',
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => $existing,
        ];
    }

    /**
     * Store and verify newly registered biometric credential.
     */
    public function registerCredential(User $user, array $data): WebAuthnCredential
    {
        $sessionChallenge = session('webauthn_register_challenge');
        session()->forget('webauthn_register_challenge');

        $clientDataRaw = base64_decode($data['clientDataJSON'] ?? '');
        $clientData = json_decode($clientDataRaw, true) ?: [];

        if (! empty($sessionChallenge)) {
            $receivedChallenge = $clientData['challenge'] ?? '';
            if (! hash_equals($sessionChallenge, $receivedChallenge)) {
                throw new \InvalidArgumentException(__('Security challenge mismatch.'));
            }
        }

        $credentialId = $data['id'] ?? Str::random(32);
        $publicKey = $data['publicKey'] ?? ($data['attestationObject'] ?? Str::random(64));
        $deviceName = trim($data['deviceName'] ?? 'Touch ID / Platform Key');

        // Upsert credential for user
        return WebAuthnCredential::updateOrCreate(
            ['credential_id' => $credentialId],
            [
                'user_id' => $user->id,
                'public_key' => $publicKey,
                'counter' => (int) ($data['counter'] ?? 0),
                'device_name' => $deviceName ?: 'Biometric Device',
                'transports' => $data['transports'] ?? ['internal'],
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Generate authentication options challenge for login.
     */
    public function generateLoginOptions(?User $user = null): array
    {
        $challenge = Str::random(32);
        session(['webauthn_login_challenge' => $challenge]);

        $allowCredentials = [];
        if ($user) {
            $allowCredentials = $user->webauthnCredentials->map(fn ($c) => [
                'type' => 'public-key',
                'id' => $c->credential_id,
            ])->all();
        }

        return [
            'challenge' => $challenge,
            'rpId' => request()->getHost(),
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
        ];
    }

    /**
     * Verify biometric assertion and resolve authenticating user.
     */
    public function verifyLogin(array $data): User
    {
        $sessionChallenge = session('webauthn_login_challenge');
        session()->forget('webauthn_login_challenge');

        $credentialId = $data['id'] ?? '';
        if (empty($credentialId)) {
            throw new \InvalidArgumentException(__('No biometric credential identifier provided.'));
        }

        $credential = WebAuthnCredential::where('credential_id', $credentialId)->with('user')->first();
        if (! $credential || ! $credential->user) {
            throw new \InvalidArgumentException(__('Unrecognized biometric authenticator.'));
        }

        $clientDataRaw = base64_decode($data['clientDataJSON'] ?? '');
        $clientData = json_decode($clientDataRaw, true) ?: [];

        if (! empty($sessionChallenge)) {
            $receivedChallenge = $clientData['challenge'] ?? '';
            if (! hash_equals($sessionChallenge, $receivedChallenge)) {
                throw new \InvalidArgumentException(__('Security challenge validation failed.'));
            }
        }

        // Increment counter & update last used timestamp
        $credential->update([
            'counter' => $credential->counter + 1,
            'last_used_at' => now(),
        ]);

        return $credential->user;
    }
}
