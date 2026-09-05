<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Room;
use App\Services\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $sessionId = $request->session()->getId();

        AccessLog::where('room_id', $room->id)
            ->where('session_id', $sessionId)
            ->update([
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'last_seen_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
        ]);
    }
}

