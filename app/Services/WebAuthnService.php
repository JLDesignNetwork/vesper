<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Passkey (WebAuthn) registration and assertion, verified on the server.
 *
 * Registration checks the single-use challenge, the origin, the relying-party ID hash and
 * the user-presence flag, then stores the credential's public key as PEM. Assertions are
 * checked the same way and their signature is verified against that key with OpenSSL
 * (ES256 and RS256). Attestation statements are not verified (attestation: "none").
 *
 * Credentials enrolled before AUTH-ISSUE-06 stored the raw attestation object instead of
 * a key; the key is extracted from it on first use and saved.
 */
class WebAuthnService
{
    private const string LOGIN_CHALLENGE_KEY = 'webauthn_login_challenge';

    private const string REGISTER_CHALLENGE_KEY = 'webauthn_register_challenge';

    private const int FLAG_USER_PRESENT = 0x01;

    private const int FLAG_ATTESTED_CREDENTIAL = 0x40;

    private const int ALG_ES256 = -7;

    private const int ALG_RS256 = -257;

    public function __construct(
        protected CborDecoder $cbor
    ) {}

    /**
     * Generate registration options challenge for an authenticated member.
     */
    public function generateRegisterOptions(User $user): array
    {
        $challenge = Str::random(32);
        session([self::REGISTER_CHALLENGE_KEY => $challenge]);

        $existing = $user->webauthnCredentials->map(fn ($c) => [
            'type' => 'public-key',
            'id' => $c->credential_id,
        ])->all();

        return [
            'challenge' => $challenge,
            'rp' => [
                'name' => config('app.name', 'Vesper'),
                'id' => $this->relyingPartyId(),
            ],
            'user' => [
                'id' => base64_encode((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => self::ALG_ES256],
                ['type' => 'public-key', 'alg' => self::ALG_RS256],
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
     * Verify a newly created credential and store its public key.
     *
     * @param  array{id?: string, clientDataJSON?: ?string, attestationObject?: ?string, deviceName?: ?string, transports?: ?array}  $data
     *
     * @throws InvalidArgumentException when the credential cannot be verified
     */
    public function registerCredential(User $user, array $data): WebAuthnCredential
    {
        $challenge = session()->pull(self::REGISTER_CHALLENGE_KEY);
        $clientDataRaw = $this->decodeBinary($data['clientDataJSON'] ?? '');
        $this->assertClientData($clientDataRaw, 'webauthn.create', $challenge);

        $attestation = $this->cbor->decode($this->decodeBinary($data['attestationObject'] ?? ''));
        if (! is_array($attestation) || ! is_string($attestation['authData'] ?? null)) {
            throw new InvalidArgumentException(__('The passkey response is incomplete. Please try again.'));
        }

        $authData = $this->parseAuthenticatorData($attestation['authData']);
        if ($authData['credential_id'] === null || $authData['public_key_pem'] === null) {
            throw new InvalidArgumentException(__('The passkey response is incomplete. Please try again.'));
        }

        $credentialId = $this->base64UrlEncode($authData['credential_id']);
        if (! hash_equals($credentialId, $this->normalizeBase64Url((string) ($data['id'] ?? '')))) {
            throw new InvalidArgumentException(__('The passkey response does not match its credential.'));
        }

        if (WebAuthnCredential::where('credential_id', $credentialId)->exists()) {
            throw new InvalidArgumentException(__('This passkey is already registered.'));
        }

        $deviceName = trim((string) ($data['deviceName'] ?? '')) ?: 'Biometric Device';

        return WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => $authData['public_key_pem'],
            'counter' => $authData['sign_count'],
            'device_name' => Str::limit($deviceName, 80, ''),
            'transports' => $data['transports'] ?? ['internal'],
            'last_used_at' => now(),
        ]);
    }

    /**
     * Generate assertion options. With a user, only their credentials are allowed.
     * The challenge is stored in the session under $challengeKey.
     */
    public function generateLoginOptions(?User $user = null, string $challengeKey = self::LOGIN_CHALLENGE_KEY): array
    {
        $challenge = Str::random(32);
        session([$challengeKey => $challenge]);

        $allowCredentials = [];
        if ($user) {
            $allowCredentials = $user->webauthnCredentials->map(fn ($c) => [
                'type' => 'public-key',
                'id' => $c->credential_id,
            ])->all();
        }

        return [
            'challenge' => $challenge,
            'rpId' => $this->relyingPartyId(),
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
        ];
    }

    /**
     * Verify a sign-in assertion and return the member it belongs to.
     *
     * @throws InvalidArgumentException when the assertion cannot be verified
     */
    public function verifyLogin(array $data): User
    {
        return $this->verifyAssertion($data, self::LOGIN_CHALLENGE_KEY);
    }

    /**
     * Verify an assertion against the challenge stored under $challengeKey. When
     * $expectedUser is given, the credential must belong to that member.
     *
     * @param  array{id?: string, clientDataJSON?: ?string, authenticatorData?: ?string, signature?: ?string}  $data
     *
     * @throws InvalidArgumentException when the assertion cannot be verified
     */
    public function verifyAssertion(array $data, string $challengeKey, ?User $expectedUser = null): User
    {
        $challenge = session()->pull($challengeKey);

        $credential = WebAuthnCredential::where('credential_id', $this->normalizeBase64Url((string) ($data['id'] ?? '')))
            ->with('user')
            ->first();

        if (! $credential || ! $credential->user || ($expectedUser && ! $credential->user->is($expectedUser))) {
            throw new InvalidArgumentException(__('Unrecognized biometric authenticator.'));
        }

        $clientDataRaw = $this->decodeBinary($data['clientDataJSON'] ?? '');
        $this->assertClientData($clientDataRaw, 'webauthn.get', $challenge);

        $authDataRaw = $this->decodeBinary($data['authenticatorData'] ?? '');
        $authData = $this->parseAuthenticatorData($authDataRaw);

        $publicKey = $this->publicKeyPemFor($credential);
        $signature = $this->decodeBinary($data['signature'] ?? '');
        $signedData = $authDataRaw.hash('sha256', $clientDataRaw, true);

        if ($signature === '' || openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidArgumentException(__('Biometric signature verification failed.'));
        }

        $storedCount = (int) $credential->counter;
        if (($authData['sign_count'] > 0 || $storedCount > 0) && $authData['sign_count'] <= $storedCount) {
            throw new InvalidArgumentException(__('This passkey may have been cloned. Remove it and enroll it again.'));
        }

        $credential->update([
            'counter' => $authData['sign_count'],
            'last_used_at' => now(),
        ]);

        return $credential->user;
    }

    /**
     * Check the client data type, the single-use challenge and the origin.
     */
    protected function assertClientData(string $clientDataRaw, string $expectedType, mixed $challenge): void
    {
        $clientData = json_decode($clientDataRaw, true);

        if (! is_array($clientData) || ($clientData['type'] ?? null) !== $expectedType) {
            throw new InvalidArgumentException(__('The passkey response is incomplete. Please try again.'));
        }

        if (! is_string($challenge) || $challenge === ''
            || ! hash_equals($this->normalizeBase64Url($challenge), $this->normalizeBase64Url((string) ($clientData['challenge'] ?? '')))) {
            throw new InvalidArgumentException(__('Security challenge validation failed.'));
        }

        if (($clientData['origin'] ?? null) !== request()->getSchemeAndHttpHost()) {
            throw new InvalidArgumentException(__('This passkey response came from a different site.'));
        }
    }

    /**
     * Parse authenticator data: relying-party ID hash, flags, signature counter and, when
     * present, the attested credential ID and public key.
     *
     * @return array{flags: int, sign_count: int, credential_id: ?string, public_key_pem: ?string}
     */
    protected function parseAuthenticatorData(string $authData): array
    {
        if (strlen($authData) < 37) {
            throw new InvalidArgumentException(__('The passkey response is incomplete. Please try again.'));
        }

        if (! hash_equals(hash('sha256', $this->relyingPartyId(), true), substr($authData, 0, 32))) {
            throw new InvalidArgumentException(__('This passkey response came from a different site.'));
        }

        $flags = ord($authData[32]);
        if (($flags & self::FLAG_USER_PRESENT) === 0) {
            throw new InvalidArgumentException(__('User presence verification failed.'));
        }

        $parsed = [
            'flags' => $flags,
            'sign_count' => unpack('N', substr($authData, 33, 4))[1],
            'credential_id' => null,
            'public_key_pem' => null,
        ];

        if (($flags & self::FLAG_ATTESTED_CREDENTIAL) !== 0 && strlen($authData) >= 55) {
            $idLength = unpack('n', substr($authData, 53, 2))[1];
            $parsed['credential_id'] = substr($authData, 55, $idLength);
            $coseKey = $this->cbor->decode(substr($authData, 55 + $idLength));
            $parsed['public_key_pem'] = is_array($coseKey) ? $this->coseKeyToPem($coseKey) : null;
        }

        return $parsed;
    }

    /**
     * The stored PEM key, upgrading legacy credentials that stored the attestation object.
     */
    protected function publicKeyPemFor(WebAuthnCredential $credential): string
    {
        if (str_starts_with((string) $credential->public_key, '-----BEGIN PUBLIC KEY-----')) {
            return $credential->public_key;
        }

        try {
            $attestation = $this->cbor->decode($this->decodeBinary((string) $credential->public_key));
            $pem = is_array($attestation) && is_string($attestation['authData'] ?? null)
                ? $this->parseAuthenticatorData($attestation['authData'])['public_key_pem']
                : null;
        } catch (InvalidArgumentException) {
            $pem = null;
        }

        if ($pem === null) {
            throw new InvalidArgumentException(__('This passkey was enrolled in an older format. Remove it in your profile and enroll it again.'));
        }

        $credential->update(['public_key' => $pem]);

        return $pem;
    }

    /**
     * Convert a COSE_Key (ES256 P-256 or RS256) to a PEM SubjectPublicKeyInfo.
     *
     * @param  array<int, mixed>  $key
     */
    protected function coseKeyToPem(array $key): ?string
    {
        $der = match (true) {
            ($key[1] ?? null) === 2 && ($key[3] ?? null) === self::ALG_ES256 && ($key[-1] ?? null) === 1
                && is_string($key[-2] ?? null) && is_string($key[-3] ?? null) && strlen($key[-2]) === 32 && strlen($key[-3]) === 32 => hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').chr(4).$key[-2].$key[-3],
            ($key[1] ?? null) === 3 && ($key[3] ?? null) === self::ALG_RS256 && is_string($key[-1] ?? null) && is_string($key[-2] ?? null) => $this->derSequence(
                $this->derSequence(hex2bin('06092a864886f70d0101010500'))
                .$this->derBitString($this->derSequence($this->derInteger($key[-1]).$this->derInteger($key[-2])))
            ),
            default => null,
        };

        return $der === null
            ? null
            : "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----';
    }

    protected function derSequence(string $contents): string
    {
        return chr(0x30).$this->derLength(strlen($contents)).$contents;
    }

    protected function derBitString(string $contents): string
    {
        return chr(0x03).$this->derLength(strlen($contents) + 1).chr(0).$contents;
    }

    protected function derInteger(string $unsigned): string
    {
        $unsigned = ltrim($unsigned, "\0") ?: "\0";
        if (ord($unsigned[0]) & 0x80) {
            $unsigned = "\0".$unsigned;
        }

        return chr(0x02).$this->derLength(strlen($unsigned)).$unsigned;
    }

    protected function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    protected function relyingPartyId(): string
    {
        return request()->getHost();
    }

    /**
     * Decode standard or URL-safe base64, with or without padding.
     */
    protected function decodeBinary(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    protected function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    protected function normalizeBase64Url(string $value): string
    {
        return rtrim(strtr($value, '+/', '-_'), '=');
    }
}
