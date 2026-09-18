<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Room;
use App\Models\User;
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
        $viewer = Auth::user();

        $logs = AccessLog::with('user')
            ->where('room_id', $room->id)
            ->where('last_seen_at', '>=', $activeThreshold)
            ->orderBy('last_seen_at', 'desc')
            ->get();

        $unlinkedAliases = $logs->whereNull('user_id')->pluck('alias')->filter()->unique();
        $usersByAlias = $unlinkedAliases->isNotEmpty()
            ? User::whereIn('name', $unlinkedAliases)->get()->keyBy('name')
            : collect();

        $operatives = $logs->map(function (AccessLog $log) use ($viewer, $usersByAlias): array {
            $user = $log->user ?: ($log->alias ? ($usersByAlias[$log->alias] ?? null) : null);
            $isLocationHidden = (bool) ($user?->hide_location);
            $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $user && $viewer->id === $user->id);

            // If location is hidden, coordinates are NEVER published to in-channel maps
            if ($isLocationHidden) {
                $lat = null;
                $lon = null;
                if ($canViewPrivate) {
                    $city = $log->city;
                    $region = $log->region;
                    $country = $log->country;
                    $countryCode = $log->country_code;
                    $flag = $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐';
                } else {
                    $city = null;
                    $region = null;
                    $country = null;
                    $countryCode = null;
                    $flag = '🔒';
                }
            } else {
                $lat = (float) $log->latitude;
                $lon = (float) $log->longitude;
                $city = $log->city;
                $region = $log->region;
                $country = $log->country;
                $countryCode = $log->country_code;
                $flag = $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐';
            }

            $ipAddress = ($canViewPrivate || ! $isLocationHidden)
                ? $log->ip_address
                : ($user ? '***.***.***.***' : $log->ip_address);

            return [
                'id' => $log->id,
                'alias' => $log->alias ?: 'Member',
                'ip_address' => $ipAddress,
                'city' => $city,
                'region' => $region,
                'country' => $country,
                'country_code' => $countryCode,
                'flag' => $flag,
                'latitude' => $lat,
                'longitude' => $lon,
                'location_hidden' => $isLocationHidden,
                'isp' => $log->isp,
                'user_agent' => $log->user_agent,
                'last_seen' => $log->last_seen_at?->diffForHumans() ?? 'Active',
            ];
        });

        $recentLogs = AccessLog::with('user')
            ->where('room_id', $room->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $recentUnlinked = $recentLogs->whereNull('user_id')->pluck('alias')->filter()->unique();
        $recentUsersByAlias = $recentUnlinked->isNotEmpty()
            ? User::whereIn('name', $recentUnlinked)->get()->keyBy('name')
            : collect();

        $recentEntries = $recentLogs->map(function (AccessLog $log) use ($viewer, $recentUsersByAlias): array {
            $user = $log->user ?: ($log->alias ? ($recentUsersByAlias[$log->alias] ?? null) : null);
            $isLocationHidden = (bool) ($user?->hide_location);
            $canViewPrivate = ($viewer && $viewer->isAdmin()) || ($viewer && $user && $viewer->id === $user->id);

            if ($isLocationHidden && ! $canViewPrivate) {
                $city = null;
                $country = null;
                $flag = '🔒';
                $ipAddress = '***.***.***.***';
            } else {
                $city = $log->city;
                $country = $log->country;
                $flag = $log->country_code ? $this->geoLocationService->countryCodeToFlag($log->country_code) : '🌐';
                $ipAddress = $log->ip_address;
            }

            return [
                'alias' => $log->alias ?: 'Member',
                'ip_address' => $ipAddress,
                'city' => $city,
                'country' => $country,
                'flag' => $flag,
                'location_hidden' => $isLocationHidden,
                'timestamp' => $log->created_at?->toIso8601String() ?? '',
                'human_time' => $log->created_at?->diffForHumans() ?? '',
            ];
        });

        return response()->json([
            'members' => $operatives,
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
        $alias = $request->session()->get("room_alias_{$room->id}", $user?->name ?: 'Member');

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
