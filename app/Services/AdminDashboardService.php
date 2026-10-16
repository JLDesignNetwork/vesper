<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the admin panel, scoped to the viewer (owner decision Q9):
 * Admin I sees everything; Admin II sees every channel and member without
 * geolocation, IPs or access-log locations; Admin III sees only their own channels
 * and those channels' members, without geolocation, IPs, locations or emails.
 */
class AdminDashboardService
{
    public function __construct(
        public GeoLocationService $geoLocationService
    ) {}

    /**
     * Aggregate core metrics for the Admin Dashboard (Overview).
     */
    public function getDashboardData(User $viewer): array
    {
        return $this->getOverviewData($viewer);
    }

    /**
     * Overview page dataset.
     */
    public function getOverviewData(User $viewer): array
    {
        $rooms = $this->roomsQuery($viewer)->withCount('messages')->with('creator:id,name,tag')->latest()->get();
        $registeredUsers = $this->membersQuery($viewer)->with('latestAccessLog')->latest()->get();
        $messages = Message::whereIn('room_id', $rooms->pluck('id'));
        $recentVisitors = $this->getRecentVisitors($viewer, 50);

        return [
            'panelScope' => $viewer->adminPanelScope(),
            'totalRooms' => $rooms->count(),
            'activeRooms' => $rooms->where('status', 'active')->count(),
            'totalMessages' => (clone $messages)->count(),
            'totalVisitors' => $this->accessLogsQuery($viewer)->count(),
            'totalUsers' => $registeredUsers->count(),
            'registeredUsers' => $registeredUsers,
            'formattedStorage' => $this->formatBytes((int) (clone $messages)->sum('attachment_size')),
            'rooms' => $rooms,
            'recentVisitors' => $recentVisitors,
            'mapMarkers' => $this->showsGeolocation($viewer) ? $this->buildMapMarkers($registeredUsers, $recentVisitors) : collect(),
            'auditEntries' => $this->getAuditEntries($viewer, 10),
        ];
    }

    /**
     * Channels management page dataset.
     */
    public function getChannelsData(User $viewer): array
    {
        $rooms = $this->roomsQuery($viewer)
            ->withCount(['messages', 'members', 'pins'])
            ->withCount(['invitations as pending_invites_count' => fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))])
            ->with('creator:id,name,tag')
            ->latest()
            ->get();

        return [
            'panelScope' => $viewer->adminPanelScope(),
            'rooms' => $rooms,
            'registeredUsers' => $this->membersQuery($viewer)->select('id', 'name', 'email', 'rank', 'tag')->get(),
            'totalRooms' => $rooms->count(),
            'activeRooms' => $rooms->where('status', 'active')->count(),
            'archivedRooms' => $rooms->where('status', 'archived')->count(),
        ];
    }

    /**
     * Members directory dataset.
     */
    public function getMembersData(User $viewer): array
    {
        $registeredUsers = $this->membersQuery($viewer)->with(['latestAccessLog', 'rooms:id,code,title,created_by_user_id'])->latest()->get();

        return [
            'panelScope' => $viewer->adminPanelScope(),
            'registeredUsers' => $registeredUsers,
            'totalUsers' => $registeredUsers->count(),
            'adminCount' => $registeredUsers->filter(fn (User $u): bool => $u->displaysAsAdmin())->count(),
            'memberCount' => $registeredUsers->reject(fn (User $u): bool => $u->displaysAsAdmin())->count(),
            'gpsVerifiedCount' => $this->showsGeolocation($viewer) ? $registeredUsers->filter(fn (User $u): bool => $u->hasGps())->count() : null,
        ];
    }

    /**
     * Global network map dataset (Admin I only; the route is gated on viewGeolocation).
     */
    public function getIntelData(User $viewer): array
    {
        $registeredUsers = User::with('latestAccessLog')->latest()->get();
        $recentVisitors = $this->getRecentVisitors($viewer, 100);
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
     * Access and audit logs dataset.
     */
    public function getLogsData(User $viewer, int $limit = 100): array
    {
        return [
            'panelScope' => $viewer->adminPanelScope(),
            'recentVisitors' => $this->getRecentVisitors($viewer, $limit),
            'totalLogs' => $this->accessLogsQuery($viewer)->count(),
            'auditEntries' => $this->getAuditEntries($viewer, $limit),
        ];
    }

    /**
     * Everything the channel profile modal shows about one channel.
     *
     * @return array<string, mixed>
     */
    public function getChannelProfile(User $viewer, Room $room): array
    {
        $room->loadCount(['messages', 'pins'])->load('creator:id,name,tag');

        $memberships = DB::table('room_user')
            ->join('users', 'users.id', '=', 'room_user.user_id')
            ->leftJoin('users as inviters', 'inviters.id', '=', 'room_user.invited_by_user_id')
            ->leftJoin('users as kickers', 'kickers.id', '=', 'room_user.kicked_by_user_id')
            ->where('room_user.room_id', $room->id)
            ->orderByDesc('room_user.created_at')
            ->get([
                'users.id', 'users.name', 'users.tag', 'room_user.role', 'room_user.status',
                'room_user.created_at as joined_at', 'room_user.last_accessed_at', 'room_user.kicked_at',
                'inviters.name as invited_by_name', 'kickers.name as kicked_by_name',
            ])
            ->groupBy('status');

        return [
            'room' => $room,
            'showsGeolocation' => $this->showsGeolocation($viewer),
            'members' => $memberships->get('active', collect()),
            'pendingInvitees' => $memberships->get('invited', collect()),
            'declinedInvitees' => $memberships->get('declined', collect()),
            'removedMembers' => $memberships->get('kicked', collect()),
            'inviteLinks' => $room->invitations()->with('creator:id,name')->latest()->get()
                ->filter(fn ($invitation): bool => $invitation->isValid())
                ->values(),
        ];
    }

    /**
     * Whether the viewer may open this channel's profile in the admin panel.
     */
    public function canViewChannel(User $viewer, Room $room): bool
    {
        $scope = $viewer->adminPanelScope();

        return $scope !== null && ($scope->seesAllChannels() || $room->isCreatedBy($viewer));
    }

    /**
     * Recent access-log entries in the viewer's scope. IP addresses, user agents and
     * locations are left out unless the viewer may see geolocation.
     */
    public function getRecentVisitors(User $viewer, int $limit = 50): Collection
    {
        $showsGeolocation = $this->showsGeolocation($viewer);

        return $this->accessLogsQuery($viewer)
            ->with(['room:id,code,title', 'user:id,name,rank,avatar_path'])
            ->latest('last_seen_at')
            ->limit($limit)
            ->get()
            ->map(function (AccessLog $log) use ($showsGeolocation): array {
                $flag = $log->country_code
                    ? $this->geoLocationService->countryCodeToFlag($log->country_code)
                    : '🌐';

                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'is_user' => (bool) $log->user_id,
                    'alias' => $log->user?->name ?: ($log->alias ?: 'Guest'),
                    'role' => $log->user ? strtolower($log->user->rank->publicLabel()) : null,
                    'avatar_url' => $log->user?->avatarUrl(),
                    'ip_address' => $showsGeolocation ? $log->ip_address : null,
                    'country' => $showsGeolocation ? ($log->country ?: 'Unknown') : null,
                    'city' => $showsGeolocation ? ($log->city ?: 'Unknown') : null,
                    'flag' => $showsGeolocation ? $flag : null,
                    'latitude' => $showsGeolocation ? (float) $log->latitude : null,
                    'longitude' => $showsGeolocation ? (float) $log->longitude : null,
                    'user_agent' => $showsGeolocation ? $log->user_agent : null,
                    'room_code' => $log->room?->code ?? 'N/A',
                    'room_title' => $log->room?->title ?? $log->room?->code ?? 'N/A',
                    'last_seen_human' => $log->last_seen_at?->diffForHumans() ?? 'Recently',
                ];
            });
    }

    /**
     * Audit log entries the viewer may read (Admin I: all; Admin II: moderation; Admin III: none).
     *
     * @return Collection<int, AdminAuditLog>
     */
    public function getAuditEntries(User $viewer, int $limit = 50): Collection
    {
        return AdminAuditLog::visibleTo($viewer)
            ->with(['actor:id,name,tag', 'subject:id,name,tag', 'room:id,code,title'])
            ->latest('id')
            ->limit($limit)
            ->get();
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
                    'role' => strtolower($u->rank->publicLabel()),
                    'avatar_url' => $u->avatarUrl(),
                    'country' => $u->country ?: ($u->location ?: 'GPS Verified'),
                    'city' => $u->city ?: 'Geolocated Node',
                    'flag' => $flag,
                    'latitude' => (float) $u->latitude,
                    'longitude' => (float) $u->longitude,
                    'ip_address' => $u->latestIp() ?? 'Verified GPS Node',
                    'room_code' => $u->displaysAsAdmin() ? 'Command HQ' : 'Member',
                    'room_title' => $u->displaysAsAdmin() ? 'Admin Command' : 'Registered Member',
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

    /**
     * Channels in the viewer's scope.
     *
     * @return Builder<Room>
     */
    protected function roomsQuery(User $viewer): Builder
    {
        return Room::query()->when(
            ! $viewer->adminPanelScope()?->seesAllChannels(),
            fn (Builder $query) => $query->where('created_by_user_id', $viewer->id),
        );
    }

    /**
     * Members in the viewer's scope. Admin III sees members, invitees and removed
     * members of their own channels, and themself.
     *
     * @return Builder<User>
     */
    protected function membersQuery(User $viewer): Builder
    {
        return User::query()->when(
            ! $viewer->adminPanelScope()?->seesAllChannels(),
            fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('users.id', $viewer->id)
                ->orWhereIn('users.id', DB::table('room_user')
                    ->join('rooms', 'rooms.id', '=', 'room_user.room_id')
                    ->where('rooms.created_by_user_id', $viewer->id)
                    ->select('room_user.user_id'))),
        );
    }

    /**
     * Access-log entries in the viewer's scope.
     *
     * @return Builder<AccessLog>
     */
    protected function accessLogsQuery(User $viewer): Builder
    {
        return AccessLog::query()->when(
            ! $viewer->adminPanelScope()?->seesAllChannels(),
            fn (Builder $query) => $query->whereIn('room_id', Room::where('created_by_user_id', $viewer->id)->select('id')),
        );
    }

    protected function showsGeolocation(User $viewer): bool
    {
        return (bool) $viewer->adminPanelScope()?->showsGeolocation();
    }
}
