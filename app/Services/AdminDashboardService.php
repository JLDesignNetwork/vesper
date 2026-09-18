<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function __construct(
        public GeoLocationService $geoLocationService
    ) {}

    /**
     * Aggregate core telemetry and operational metrics for the Admin Dashboard (Overview).
     */
    public function getDashboardData(): array
    {
        return $this->getOverviewData();
    }

    /**
     * Overview page dataset.
     */
    public function getOverviewData(): array
    {
        $totalRooms = Room::count();
        $activeRooms = Room::where('status', 'active')->count();
        $totalMessages = Message::count();
        $totalVisitors = AccessLog::count();
        $totalUsers = User::count();
        $registeredUsers = User::with('latestAccessLog')->latest()->get();

        $totalBytes = (int) Message::sum('attachment_size');
        $formattedStorage = $this->formatBytes($totalBytes);

        $rooms = Room::withCount('messages')->latest()->get();

        $recentVisitors = $this->getRecentVisitors(50);
        $mapMarkers = $this->buildMapMarkers($registeredUsers, $recentVisitors);

        return [
            'totalRooms' => $totalRooms,
            'activeRooms' => $activeRooms,
            'totalMessages' => $totalMessages,
            'totalVisitors' => $totalVisitors,
            'totalUsers' => $totalUsers,
            'registeredUsers' => $registeredUsers,
            'formattedStorage' => $formattedStorage,
            'rooms' => $rooms,
            'recentVisitors' => $recentVisitors,
            'mapMarkers' => $mapMarkers,
        ];
    }

    /**
     * Channels management page dataset.
     */
    public function getChannelsData(): array
    {
        $rooms = Room::withCount('messages')->with('creator:id,name')->latest()->get();
        $registeredUsers = User::select('id', 'name', 'email', 'role')->get();

        return [
            'rooms' => $rooms,
            'registeredUsers' => $registeredUsers,
            'totalRooms' => $rooms->count(),
            'activeRooms' => $rooms->where('status', 'active')->count(),
            'archivedRooms' => $rooms->where('status', 'archived')->count(),
        ];
    }

    /**
     * Members directory dataset.
     */
    public function getMembersData(): array
    {
        $registeredUsers = User::with(['latestAccessLog', 'rooms:id,code,title'])->latest()->get();

        return [
            'registeredUsers' => $registeredUsers,
            'totalUsers' => $registeredUsers->count(),
            'adminCount' => $registeredUsers->where('role', 'admin')->count(),
            'memberCount' => $registeredUsers->where('role', 'member')->count(),
            'gpsVerifiedCount' => $registeredUsers->filter(fn (User $u): bool => $u->hasGps())->count(),
        ];
    }

    /**
     * Backward-compatibility alias for getMembersData.
     */
    public function getOperativesData(): array
    {
        return $this->getMembersData();
    }

    /**
     * Global intelligence satellite radar dataset.
     */
    public function getIntelData(): array
    {
        $registeredUsers = User::with('latestAccessLog')->latest()->get();
        $recentVisitors = $this->getRecentVisitors(100);
        $mapMarkers = $this->buildMapMarkers($registeredUsers, $recentVisitors);

        return [
            'registeredUsers' => $registeredUsers,
            'recentVisitors' => $recentVisitors,
            'mapMarkers' => $mapMarkers,
            'totalNodes' => $mapMarkers->count(),
            'registeredNodes' => $registeredUsers->filter(fn (User $u): bool => $u->hasGps())->count(),
            'visitorNodes' => $mapMarkers->where('is_user', false)->count(),
        ];
    }

    /**
     * Transmission & connection logs dataset.
     */
    public function getLogsData(int $limit = 100): array
    {
        $recentVisitors = $this->getRecentVisitors($limit);

        return [
            'recentVisitors' => $recentVisitors,
            'totalLogs' => AccessLog::count(),
        ];
    }

    /**
     * Retrieve recent visitor telemetry logs with localized flags and humanized timestamps.
     */
    public function getRecentVisitors(int $limit = 50): Collection
    {
        return AccessLog::with(['room:id,code,title', 'user:id,name,role,avatar_path'])
            ->latest('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(function (AccessLog $log): array {
                $flag = $log->country_code
                    ? $this->geoLocationService->countryCodeToFlag($log->country_code)
                    : '🌐';

                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'is_user' => (bool) $log->user_id,
                    'alias' => $log->user?->name ?: ($log->alias ?: 'Guest'),
                    'role' => $log->user?->role,
                    'avatar_url' => $log->user?->avatarUrl(),
                    'ip_address' => $log->ip_address,
                    'country' => $log->country ?: 'Unknown',
                    'city' => $log->city ?: 'Unknown',
                    'flag' => $flag,
                    'latitude' => (float) $log->latitude,
                    'longitude' => (float) $log->longitude,
                    'user_agent' => $log->user_agent,
                    'room_code' => $log->room?->code ?? 'N/A',
                    'room_title' => $log->room?->title ?? $log->room?->code ?? 'N/A',
                    'last_seen_human' => $log->last_seen_at?->diffForHumans() ?? 'Recently',
                ];
            });
    }

    /**
     * Construct unified geo-location markers for Leaflet mapping.
     */
    public function buildMapMarkers(Collection $registeredUsers, Collection $recentVisitors): Collection
    {
        $userMarkers = $registeredUsers
            ->filter(fn (User $u): bool => $u->hasGps())
            ->map(function (User $u): array {
                $flag = $u->country_code
                    ? $this->geoLocationService->countryCodeToFlag($u->country_code)
                    : '📍';

                return [
                    'id' => 'user_'.$u->id,
                    'user_id' => $u->id,
                    'is_user' => true,
                    'alias' => $u->name,
                    'role' => $u->role,
                    'avatar_url' => $u->avatarUrl(),
                    'country' => $u->country ?: ($u->location ?: 'GPS Verified'),
                    'city' => $u->city ?: 'Geolocated Node',
                    'flag' => $flag,
                    'latitude' => (float) $u->latitude,
                    'longitude' => (float) $u->longitude,
                    'ip_address' => $u->latestIp() ?? 'Verified GPS Node',
                    'room_code' => $u->isAdmin() ? 'Command HQ' : 'Member',
                    'room_title' => $u->isAdmin() ? 'Admin Command' : 'Registered Member',
                    'last_seen_human' => $u->location_synced_at?->diffForHumans() ?? 'Synced',
                ];
            });

        $visitorMarkers = $recentVisitors
            ->filter(fn (array $v): bool => ! empty($v['latitude']) && ! empty($v['longitude']))
            ->filter(fn (array $v): bool => empty($v['user_id']) || ! $userMarkers->contains('user_id', $v['user_id']));

        return $userMarkers->concat($visitorMarkers)->values();
    }

    /**
     * Format raw byte count into a human-readable storage string.
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
