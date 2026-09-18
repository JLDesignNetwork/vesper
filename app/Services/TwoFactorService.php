<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorService
{
    protected const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically random 16-character Base32 secret key.
     */
    public function generateSecretKey(int $length = 16): string
    {
        $secret = '';
        $alphabetLength = strlen(self::BASE32_ALPHABET);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $secret;
    }

    /**
     * Compute a 6-digit TOTP token for a given Base32 secret at a specific timestamp.
     */
    public function calculateTotp(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = floor($timestamp / 30);

        $binarySecret = $this->base32Decode($secret);

        // Pack 64-bit integer into big-endian bytes
        $timeBytes = pack('N*', 0).pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $binarySecret, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $binaryCode = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $binaryCode % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code against a secret with time-drift tolerance.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $cleanCode = trim(str_replace([' ', '-'], '', $code));

        if (strlen($cleanCode) !== 6 || ! ctype_digit($cleanCode)) {
            return false;
        }

        $currentTime = time();

        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $currentTime + ($i * 30);
            if (hash_equals($this->calculateTotp($secret, $testTime), $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate the standard `otpauth://` URI recognized by authenticator apps.
     */
    public function getOtpAuthUrl(string $account, string $secret, string $issuer = 'Vesper'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($account);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate an inline SVG QR code without external network calls.
     */
    public function getInlineSvgQrCode(string $otpAuthUrl, int $size = 200): string
    {
        try {
            $renderer = new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);

            return $writer->writeString($otpAuthUrl);
        } catch (\Throwable $e) {
            // Fallback to QuickChart URL if BaconQrCode encounters rendering issue
            return '<img src="'.htmlspecialchars($this->getQrCodeUrl($otpAuthUrl), ENT_QUOTES).'" alt="2FA QR Code" class="w-48 h-48 rounded-xl border border-emerald-500/30" />';
        }
    }

    /**
     * Generate a QR code URL for rendering in the UI.
     * Uses QuickChart QR API for ultra-crisp SVG/PNG rendering with dark styling.
     */
    public function getQrCodeUrl(string $otpAuthUrl): string
    {
        $encoded = rawurlencode($otpAuthUrl);

        return "https://quickchart.io/qr?text={$encoded}&size=200&dark=10b981&light=0b0f19&ecLevel=M&margin=1";
    }

    /**
     * Decode a Base32-encoded string to binary.
     */
    public function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            if ($char === '=') {
                break;
            }

            $val = strpos(self::BASE32_ALPHABET, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
