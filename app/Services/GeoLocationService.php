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

    /**
     * Reverse geocode high-precision GPS coordinates into city, country, region, and flag.
     *
     * @return array{
     *     city: string,
     *     region: string,
     *     country: string,
     *     country_code: string,
     *     flag: string
     * }
     */
    public function reverseGeocode(float $latitude, float $longitude): array
    {
        $roundLat = round($latitude, 3);
        $roundLon = round($longitude, 3);
        $cacheKey = "reverse_geo_{$roundLat}_{$roundLon}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($latitude, $longitude): array {
            try {
                $response = Http::timeout(3)
                    ->withHeaders(['User-Agent' => 'VesperApp/1.0'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'json',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'zoom' => 10,
                        'addressdetails' => 1,
                    ]);

                if ($response->successful() && $response->json('address')) {
                    $addr = $response->json('address');
                    $city = (string) ($addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['municipality'] ?? $addr['suburb'] ?? $addr['county'] ?? 'Geolocated Point');
                    $region = (string) ($addr['state'] ?? $addr['province'] ?? $addr['region'] ?? '');
                    $country = (string) ($addr['country'] ?? 'GPS Location');
                    $countryCode = strtoupper((string) ($addr['country_code'] ?? ''));

                    return [
                        'city' => $city,
                        'region' => $region,
                        'country' => $country,
                        'country_code' => $countryCode,
                        'flag' => $countryCode ? $this->countryCodeToFlag($countryCode) : '📍',
                    ];
                }
            } catch (Throwable) {
                // Fallback gracefully on network timeout or failure
            }

            return [
                'city' => 'Geolocated Coordinate',
                'region' => '',
                'country' => 'GPS Verified',
                'country_code' => '',
                'flag' => '📍',
            ];
        });
    }

    /**
     * Map geographic location indicators (country code, country name, freeform location text)
     * to the most common supported platform language ('en', 'ru', 'fr', 'it').
     */
    public function resolveLanguageFromLocation(?string $countryCode = null, ?string $country = null, ?string $locationText = null): string
    {
        $code = strtoupper(trim((string) $countryCode));
        $countryName = strtolower(trim((string) $country));
        $text = strtolower(trim((string) $locationText));

        // 1. Italian: IT, SM (San Marino), VA (Vatican City)
        $italianCodes = ['IT', 'SM', 'VA'];
        if (in_array($code, $italianCodes, true)) {
            return 'it';
        }

        // 2. French: FR, MC (Monaco), SN (Senegal), CI (Ivory Coast), etc.
        $frenchCodes = ['FR', 'MC', 'SN', 'CI', 'CD', 'CG', 'MG', 'CM', 'BF', 'NE', 'ML', 'GN', 'TD', 'BJ', 'GA', 'DJ', 'KM', 'LU'];
        if (in_array($code, $frenchCodes, true)) {
            return 'fr';
        }

        // 3. Russian: RU, BY (Belarus), KZ (Kazakhstan), KG (Kyrgyzstan), etc.
        $russianCodes = ['RU', 'BY', 'KZ', 'KG', 'TJ', 'UZ', 'AM', 'AZ', 'MD'];
        if (in_array($code, $russianCodes, true)) {
            return 'ru';
        }

        // 4. Country name inspection
        if ($countryName !== '') {
            if (str_contains($countryName, 'italy') || str_contains($countryName, 'italia') || str_contains($countryName, 'san marino') || str_contains($countryName, 'vatican')) {
                return 'it';
            }
            if (str_contains($countryName, 'france') || str_contains($countryName, 'monaco') || str_contains($countryName, 'senegal') || str_contains($countryName, 'belgique') || str_contains($countryName, 'belgium')) {
                return 'fr';
            }
            if (str_contains($countryName, 'russia') || str_contains($countryName, 'россия') || str_contains($countryName, 'belarus') || str_contains($countryName, 'kazakhstan') || str_contains($countryName, 'kyrgyzstan')) {
                return 'ru';
            }
        }

        // 5. Freeform location text inspection (e.g. "Rome, Italy", "Paris, France", "Moscow")
        if ($text !== '') {
            if (preg_match('/\b(italy|italia|rome|roma|milan|milano|napoli|florence|firenze|venice|venezia|turin|torino|palermo)\b/u', $text)) {
                return 'it';
            }
            if (preg_match('/\b(france|paris|marseille|lyon|toulouse|nice|nantes|strasbourg|bordeaux|lille|monaco)\b/u', $text)) {
                return 'fr';
            }
            if (preg_match('/\b(russia|россия|moscow|москва|saint petersburg|петербург|saint-petersburg|novosibirsk|kazan|казань|minsk|минск|almaty|алматы|astana|астана)\b/u', $text)) {
                return 'ru';
            }
        }

        return 'en';
    }
}

