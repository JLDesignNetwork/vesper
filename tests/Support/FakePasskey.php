<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * A software passkey for tests: a real P-256 key pair that produces WebAuthn
 * registration (attestation "none") and assertion responses the server can verify.
 */
class FakePasskey
{
    public readonly string $credentialId;

    private \OpenSSLAsymmetricKey $privateKey;

    private int $signCount = 0;

    private string $origin;

    private string $rpId;

    /**
     * Defaults to the test application's URL, which is what the server checks against.
     */
    public function __construct(?string $origin = null, ?string $rpId = null)
    {
        $appUrl = parse_url(url('/'));
        $this->origin = $origin ?? $appUrl['scheme'].'://'.$appUrl['host'].(isset($appUrl['port']) ? ':'.$appUrl['port'] : '');
        $this->rpId = $rpId ?? $appUrl['host'];
        $this->credentialId = random_bytes(16);
        $this->privateKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    }

    /**
     * The credential ID as the browser reports it (base64url).
     */
    public function id(): string
    {
        return self::base64Url($this->credentialId);
    }

    /**
     * A registration response for the given challenge.
     *
     * @return array<string, string>
     */
    public function attestation(string $challenge, array $clientDataOverrides = []): array
    {
        $details = openssl_pkey_get_details($this->privateKey)['ec'];
        $coseKey = self::cborMap([1 => 2, 3 => -7, -1 => 1, -2 => self::bytes(str_pad($details['x'], 32, "\0", STR_PAD_LEFT)), -3 => self::bytes(str_pad($details['y'], 32, "\0", STR_PAD_LEFT))]);
        $authData = hash('sha256', $this->rpId, true).chr(0x45).pack('N', 0)
            .str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$coseKey;

        return [
            'id' => $this->id(),
            'clientDataJSON' => base64_encode($this->clientData('webauthn.create', $challenge, $clientDataOverrides)),
            'attestationObject' => base64_encode(self::cborMap(['fmt' => 'none', 'attStmt' => (object) ['raw' => self::cborMap([])], 'authData' => self::bytes($authData)])),
            'deviceName' => 'Test Passkey',
        ];
    }

    /**
     * A signed assertion response for the given challenge.
     *
     * @return array<string, string>
     */
    public function assertion(string $challenge, array $clientDataOverrides = [], ?int $signCount = null, ?string $rpId = null, int $flags = 0x05): array
    {
        $this->signCount = $signCount ?? $this->signCount + 1;
        $authData = hash('sha256', $rpId ?? $this->rpId, true).chr($flags).pack('N', $this->signCount);
        $clientData = $this->clientData('webauthn.get', $challenge, $clientDataOverrides);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->id(),
            'clientDataJSON' => base64_encode($clientData),
            'authenticatorData' => base64_encode($authData),
            'signature' => base64_encode($signature),
        ];
    }

    private function clientData(string $type, string $challenge, array $overrides): string
    {
        return json_encode(array_merge([
            'type' => $type,
            // Browsers base64-decode the server's challenge and report it back as base64url.
            'challenge' => self::base64Url(base64_decode(strtr($challenge, '-_', '+/'))),
            'origin' => $this->origin,
        ], $overrides));
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Mark a string as a CBOR byte string (text strings are encoded otherwise).
     */
    private static function bytes(string $value): object
    {
        return (object) ['bytes' => $value];
    }

    private static function cborMap(array $map): string
    {
        $out = self::head(5, count($map));
        foreach ($map as $key => $value) {
            $out .= self::encode($key).self::encode($value);
        }

        return $out;
    }

    private static function encode(mixed $value): string
    {
        return match (true) {
            is_int($value) && $value >= 0 => self::head(0, $value),
            is_int($value) => self::head(1, -1 - $value),
            is_object($value) && isset($value->raw) => $value->raw,
            is_object($value) => self::head(2, strlen($value->bytes)).$value->bytes,
            is_string($value) => self::head(3, strlen($value)).$value,
        };
    }

    private static function head(int $major, int $length): string
    {
        return match (true) {
            $length < 24 => chr(($major << 5) | $length),
            $length < 256 => chr(($major << 5) | 24).chr($length),
            default => chr(($major << 5) | 25).pack('n', $length),
        };
    }
}
