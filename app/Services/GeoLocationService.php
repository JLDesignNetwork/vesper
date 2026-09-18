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
     * to the most common supported platform language.
     */
    public function resolveLanguageFromLocation(?string $countryCode = null, ?string $country = null, ?string $locationText = null): string
    {
        $code = strtoupper(trim((string) $countryCode));
        $countryName = strtolower(trim((string) $country));
        $text = strtolower(trim((string) $locationText));

        // 1. Uzbek: UZ
        if ($code === 'UZ') {
            return 'uz';
        }

        // 2. Japanese: JP
        if ($code === 'JP') {
            return 'ja';
        }

        // 3. Korean: KR, KP
        if (in_array($code, ['KR', 'KP'], true)) {
            return 'ko';
        }

        // 4. Chinese: CN, TW, HK, MO
        if (in_array($code, ['CN', 'TW', 'HK', 'MO'], true)) {
            return 'zh';
        }

        // 5. Spanish: ES, MX, AR, CO, CL, PE, etc.
        $spanishCodes = ['ES', 'MX', 'AR', 'CO', 'CL', 'PE', 'VE', 'EC', 'GT', 'CU', 'DO', 'BO', 'HN', 'PY', 'SV', 'NI', 'CR', 'PA', 'UY', 'PR'];
        if (in_array($code, $spanishCodes, true)) {
            return 'es';
        }

        // 6. German: DE, AT, CH, LI
        $germanCodes = ['DE', 'AT', 'CH', 'LI'];
        if (in_array($code, $germanCodes, true)) {
            return 'de';
        }

        // 7. Portuguese: PT, BR, AO, MZ, CV, GW, ST, TL
        $portugueseCodes = ['PT', 'BR', 'AO', 'MZ', 'CV', 'GW', 'ST', 'TL'];
        if (in_array($code, $portugueseCodes, true)) {
            return 'pt';
        }

        // 8. Arabic: SA, AE, EG, QA, KW, OM, BH, JO, LB, IQ, MA, DZ, TN
        $arabicCodes = ['SA', 'AE', 'EG', 'QA', 'KW', 'OM', 'BH', 'JO', 'LB', 'IQ', 'MA', 'DZ', 'TN'];
        if (in_array($code, $arabicCodes, true)) {
            return 'ar';
        }

        // 9. Turkish: TR
        if ($code === 'TR') {
            return 'tr';
        }

        // 10. Dutch: NL
        if ($code === 'NL') {
            return 'nl';
        }

        // 11. Polish: PL
        if ($code === 'PL') {
            return 'pl';
        }

        // 12. Italian: IT, SM (San Marino), VA (Vatican City)
        $italianCodes = ['IT', 'SM', 'VA'];
        if (in_array($code, $italianCodes, true)) {
            return 'it';
        }

        // 13. French: FR, MC, SN, CI, CD, CG, MG, CM, BF, etc.
        $frenchCodes = ['FR', 'MC', 'SN', 'CI', 'CD', 'CG', 'MG', 'CM', 'BF', 'NE', 'ML', 'GN', 'TD', 'BJ', 'GA', 'DJ', 'KM', 'LU'];
        if (in_array($code, $frenchCodes, true)) {
            return 'fr';
        }

        // 14. Russian: RU, BY, KZ, KG, TJ, AM, AZ, MD
        $russianCodes = ['RU', 'BY', 'KZ', 'KG', 'TJ', 'AM', 'AZ', 'MD'];
        if (in_array($code, $russianCodes, true)) {
            return 'ru';
        }

        // 15. Country name inspection
        if ($countryName !== '') {
            if (str_contains($countryName, 'uzbekistan') || str_contains($countryName, 'oʻzbekiston')) {
                return 'uz';
            }
            if (str_contains($countryName, 'japan') || str_contains($countryName, 'nippon') || str_contains($countryName, '日本')) {
                return 'ja';
            }
            if (str_contains($countryName, 'korea') || str_contains($countryName, '한국')) {
                return 'ko';
            }
            if (str_contains($countryName, 'china') || str_contains($countryName, 'taiwan') || str_contains($countryName, 'hong kong') || str_contains($countryName, '中国')) {
                return 'zh';
            }
            if (str_contains($countryName, 'spain') || str_contains($countryName, 'españa') || str_contains($countryName, 'mexico') || str_contains($countryName, 'méxico') || str_contains($countryName, 'argentina') || str_contains($countryName, 'colombia')) {
                return 'es';
            }
            if (str_contains($countryName, 'germany') || str_contains($countryName, 'deutschland') || str_contains($countryName, 'austria') || str_contains($countryName, 'österreich') || str_contains($countryName, 'switzerland') || str_contains($countryName, 'schweiz')) {
                return 'de';
            }
            if (str_contains($countryName, 'brazil') || str_contains($countryName, 'brasil') || str_contains($countryName, 'portugal')) {
                return 'pt';
            }
            if (str_contains($countryName, 'turkey') || str_contains($countryName, 'türkiye')) {
                return 'tr';
            }
            if (str_contains($countryName, 'netherlands') || str_contains($countryName, 'nederland') || str_contains($countryName, 'holland')) {
                return 'nl';
            }
            if (str_contains($countryName, 'poland') || str_contains($countryName, 'polska')) {
                return 'pl';
            }
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

        // 16. Freeform location text inspection
        if ($text !== '') {
            if (preg_match('/\b(uzbekistan|tashkent|samarkand|bukhara|toshkent)\b/u', $text)) {
                return 'uz';
            }
            if (preg_match('/\b(japan|tokyo|osaka|kyoto|yokohama|nagoya|sapporo)\b/u', $text)) {
                return 'ja';
            }
            if (preg_match('/\b(korea|seoul|busan|incheon|daegu)\b/u', $text)) {
                return 'ko';
            }
            if (preg_match('/\b(china|beijing|shanghai|guangzhou|shenzhen|taipei|hong kong)\b/u', $text)) {
                return 'zh';
            }
            if (preg_match('/\b(spain|madrid|barcelona|valencia|seville|mexico|buenos aires|bogota|lima)\b/u', $text)) {
                return 'es';
            }
            if (preg_match('/\b(germany|berlin|munich|münchen|frankfurt|hamburg|vienna|wien|zurich|zürich)\b/u', $text)) {
                return 'de';
            }
            if (preg_match('/\b(portugal|lisbon|lisboa|porto|brazil|são paulo|sao paulo|rio de janeiro)\b/u', $text)) {
                return 'pt';
            }
            if (preg_match('/\b(turkey|istanbul|ankara|izmir)\b/u', $text)) {
                return 'tr';
            }
            if (preg_match('/\b(netherlands|amsterdam|rotterdam|the hague|den haag|utrecht)\b/u', $text)) {
                return 'nl';
            }
            if (preg_match('/\b(poland|warsaw|warszawa|krakow|kraków|gdansk|gdańsk|wroclaw|wrocław)\b/u', $text)) {
                return 'pl';
            }
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

    /**
     * Anonymize an IP address to preserve user privacy (mask last octet).
     */
    public function anonymizeIp(?string $ip): string
    {
        if (empty($ip)) {
            return '0.0.0.0';
        }

        $ip = trim($ip);

        // IPv4: Mask the last octet (e.g. 192.168.1.123 -> 192.168.1.0)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        // IPv6: Mask the interface ID (keep prefix /64)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                $masked = substr($packed, 0, 8).str_repeat("\0", 8);

                return inet_ntop($masked) ?: '::';
            }
        }

        return '127.0.0.1';
    }

    /**
     * Add deterministic radial jitter to GPS coordinates to obfuscate exact physical position.
     *
     * @return array{lat: float, lon: float}
     */
    public function jitterCoordinates(float $lat, float $lon, float $radiusKm = 3.0): array
    {
        if ($lat == 0.0 && $lon == 0.0) {
            return ['lat' => 0.0, 'lon' => 0.0];
        }

        // 1 degree latitude ~ 111 km
        $deltaLat = $radiusKm / 111.0;
        // 1 degree longitude ~ 111 * cos(lat) km
        $radLat = deg2rad($lat);
        $deltaLon = $radiusKm / (111.0 * max(cos($radLat), 0.1));

        $offsetLat = (mt_rand(-1000, 1000) / 1000.0) * $deltaLat;
        $offsetLon = (mt_rand(-1000, 1000) / 1000.0) * $deltaLon;

        return [
            'lat' => round($lat + $offsetLat, 4),
            'lon' => round($lon + $offsetLon, 4),
        ];
    }
}
