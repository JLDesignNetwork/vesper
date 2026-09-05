<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Room;
use App\Services\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IntelController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        public GeoLocationService $geoLocationService
    ) {}

    /**
     * Get live radar and geospatial tracking data for the room.
     */
    public function radar(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->where('status', 'active')->first();

        if (! $room) {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $activeThreshold = now()->subMinutes(10);
        $logs = AccessLog::where('room_id', $room->id)
            ->where('last_seen_at', '>=', $activeThreshold)
            ->orderBy('last_seen_at', 'desc')
            ->get();

        $operatives = $logs->map(function (AccessLog $log): array {
            return [
                'id' => $log->id,
                'alias' => $log->alias ?: 'Operative',
                'ip_address' => $log->ip_address,
                'city' => $log->city,
                'region' => $log->region,
                'country' => $log->country,
                'country_code' => $log->country_code,
                'flag' => $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐',
                'latitude' => (float) $log->latitude,
                'longitude' => (float) $log->longitude,
                'isp' => $log->isp,
                'user_agent' => $log->user_agent,
                'last_seen' => $log->last_seen_at?->diffForHumans() ?? 'Active',
            ];
        });

        $recentEntries = AccessLog::where('room_id', $room->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (AccessLog $log): array {
                return [
                    'alias' => $log->alias ?: 'Operative',
                    'ip_address' => $log->ip_address,
                    'city' => $log->city,
                    'country' => $log->country,
                    'flag' => $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐',
                    'timestamp' => $log->created_at?->toIso8601String() ?? '',
                    'human_time' => $log->created_at?->diffForHumans() ?? '',
                ];
            });

        return response()->json([
            'operatives' => $operatives,
            'recent_entries' => $recentEntries,
            'total_active' => $operatives->count(),
        ]);
    }

    /**
     * Update participant's precise GPS coordinates from browser Geolocation API.
     */
    public function updateGps(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->where('status', 'active')->first();

        if (! $room) {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['latitude'];
        $lon = (float) $validated['longitude'];

        // Reverse-geocode coordinates to obtain verified city and country
        $geo = $this->geoLocationService->reverseGeocode($lat, $lon);

        $sessionId = $request->session()->getId();
        $user = Auth::user();
        $userId = $user?->id;

        if ($user) {
            $user->latitude = $lat;
            $user->longitude = $lon;
            $user->city = $geo['city'];
            $user->country = $geo['country'];
            $user->country_code = $geo['country_code'];
            $user->location_synced_at = now();
            if (empty($user->location) || $user->location === 'Classified' || $user->location === '—') {
                $user->location = trim(($geo['city'] ?? '').', '.($geo['country'] ?? ''), ', ');
            }
            $user->save();

            // Synchronize all access logs for this user across all channels
            AccessLog::where('user_id', $userId)->update([
                'latitude' => $lat,
                'longitude' => $lon,
                'city' => $geo['city'],
                'region' => $geo['region'],
                'country' => $geo['country'],
                'country_code' => $geo['country_code'],
                'last_seen_at' => now(),
            ]);
        }

        $clientIp = $this->geoLocationService->getClientIp($request);
        $alias = $request->session()->get("room_alias_{$room->id}", $user?->name ?: 'Operative');

        AccessLog::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $userId,
                'alias' => $alias,
                'ip_address' => $clientIp,
                'latitude' => $lat,
                'longitude' => $lon,
                'city' => $geo['city'],
                'region' => $geo['region'],
                'country' => $geo['country'],
                'country_code' => $geo['country_code'],
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'latitude' => $lat,
            'longitude' => $lon,
            'city' => $geo['city'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'flag' => $geo['flag'],
        ]);
    }
}

