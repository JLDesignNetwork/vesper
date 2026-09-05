<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoLocationService
{
    /**
     * Resolve the client's public or real IP address from the request.
     */
    public function getClientIp(Request $request): string
    {
        $headersToCheck = [
            'CF-Connecting-IP',
            'X-Forwarded-For',
            'X-Real-IP',
        ];

        foreach ($headersToCheck as $header) {
            $value = $request->header($header);
            if (! empty($value)) {
                $ips = array_map('trim', explode(',', $value));
                if (! empty($ips[0]) && filter_var($ips[0], FILTER_VALIDATE_IP)) {
                    return $ips[0];
                }
            }
        }

        return $request->ip() ?: '127.0.0.1';
    }

    /**
     * Locate geographic coordinates and metadata for an IP address.
     *
     * @return array{
     *     ip: string,
     *     country: string,
     *     country_code: string,
     *     region: string,
     *     city: string,
     *     latitude: float,
     *     longitude: float,
     *     isp: string,
     *     flag: string
     * }
     */
    public function locate(string $ip): array
    {
        return Cache::remember("geo_location_{$ip}", now()->addDays(7), function () use ($ip): array {
            $isLocal = in_array($ip, ['127.0.0.1', '::1'], true)
                || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

            if ($isLocal) {
                return $this->resolveLocalIp();
            }

            try {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,lat,lon,isp,query");

                if ($response->successful() && $response->json('status') === 'success') {
                    $countryCode = (string) $response->json('countryCode', 'UN');

                    return [
                        'ip' => $ip,
                        'country' => (string) $response->json('country', 'Unknown'),
                        'country_code' => $countryCode,
                        'region' => (string) $response->json('regionName', 'Unknown'),
                        'city' => (string) $response->json('city', 'Unknown'),
                        'latitude' => (float) $response->json('lat', 0.0),
                        'longitude' => (float) $response->json('lon', 0.0),
                        'isp' => (string) $response->json('isp', 'Local Network'),
                        'flag' => $this->countryCodeToFlag($countryCode),
                    ];
                }
            } catch (Throwable) {
                // Fallback gracefully on external service failure or timeout
            }

            return $this->defaultLocationData($ip);
        });
    }

    /**
     * Resolve realistic developer location data for localhost/private IPs.
     *
     * @return array{
     *     ip: string,
     *     country: string,
     *     country_code: string,
     *     region: string,
     *     city: string,
     *     latitude: float,
     *     longitude: float,
     *     isp: string,
     *     flag: string
     * }
     */
    protected function resolveLocalIp(): array
    {
        return [
            'ip' => '127.0.0.1 (Localhost / Secure Node)',
            'country' => 'Local Network',
            'country_code' => 'DEV',
            'region' => 'Terminal',
            'city' => 'Command Center',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'isp' => 'Cipher Secure Local Host',
            'flag' => '🛡️',
        ];
    }

    /**
     * Generate fallback location metadata when geolocation fails.
     *
     * @return array{
     *     ip: string,
     *     country: string,
     *     country_code: string,
     *     region: string,
     *     city: string,
     *     latitude: float,
     *     longitude: float,
     *     isp: string,
     *     flag: string
     * }
     */
    protected function defaultLocationData(string $ip): array
    {
        return [
            'ip' => $ip,
            'country' => 'Unknown Origin',
            'country_code' => 'UN',
            'region' => 'Unknown',
            'city' => 'Classified',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'isp' => 'Encrypted Node',
            'flag' => '🌐',
        ];
    }

    /**
     * Convert an ISO 3166-1 alpha-2 country code into an emoji flag.
     */
    public function countryCodeToFlag(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));

        if (strlen($code) !== 2 || ! ctype_alpha($code)) {
            return '🌐';
        }

        $firstChar = mb_chr(0x1F1E6 + ord($code[0]) - ord('A'));
        $secondChar = mb_chr(0x1F1E6 + ord($code[1]) - ord('A'));

        return $firstChar.$secondChar;
    }
}
