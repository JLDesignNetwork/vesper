<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        public GeoLocationService $geoLocationService,
        public MediaStorageService $mediaStorageService
    ) {}

    /**
     * Display the Admin Dashboard with stats, channels, visitor logs, and map.
     */
    public function index(): View
    {
        $totalRooms = Room::count();
        $activeRooms = Room::where('status', 'active')->count();
        $totalMessages = Message::count();
        $totalVisitors = AccessLog::count();
        $totalUsers = User::count();
        $registeredUsers = User::with('latestAccessLog')->latest()->get();

        // Calculate total storage consumed by attachments
        $totalBytes = (int) Message::sum('attachment_size');
        $formattedStorage = $this->formatBytes($totalBytes);

        // Fetch all rooms with message counts
        $rooms = Room::withCount('messages')->latest()->get();

        // Fetch recent visitor intelligence logs
        $recentVisitors = AccessLog::with(['room:id,code,title', 'user:id,name,role,avatar_path'])
            ->latest('last_seen_at')
            ->limit(50)
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

        // Registered user GPS markers
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
                    'room_code' => $u->isAdmin() ? 'Command HQ' : 'Operative',
                    'room_title' => $u->isAdmin() ? 'Admin Command' : 'Registered Member',
                    'last_seen_human' => $u->location_synced_at?->diffForHumans() ?? 'Synced',
                ];
            });

        // Visitor access log markers (excluding already represented registered users)
        $visitorMarkers = $recentVisitors
            ->filter(fn (array $v): bool => ! empty($v['latitude']) && ! empty($v['longitude']))
            ->filter(fn (array $v): bool => empty($v['user_id']) || ! $userMarkers->contains('user_id', $v['user_id']));

        // Combined map markers
        $mapMarkers = $userMarkers->concat($visitorMarkers)->values();

        return view('admin.dashboard', [
            'adminUser' => Auth::user(),
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
        ]);
    }

    /**
     * Remove a registered member account.
     */
    public function destroyUser(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return redirect()->route('admin.dashboard')->with('error', 'You cannot delete your own administrative account.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('admin.dashboard')->with('status', "User [{$userName}] account removed.");
    }

    /**
     * Create a new secure channel.
     */
    public function storeChannel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:rooms,code'],
            'passcode' => ['nullable', 'string', 'min:4'],
            'pin' => ['nullable', 'string', 'min:4'],
            'allowed_languages' => ['nullable', 'array'],
            'allowed_languages.*' => ['string', 'in:en,ru,fr,it'],
            'expiration' => ['nullable', 'string', 'in:1h,24h,7d,permanent'],
            'burn_after_reading' => ['nullable', 'boolean'],
            'notify_admin' => ['nullable', 'boolean'],
        ]);

        $code = ! empty($validated['code'])
            ? strtoupper(trim($validated['code']))
            : strtoupper(Str::random(4).'-'.rand(1000, 9999));

        $expiresAt = match ($validated['expiration'] ?? '24h') {
            '1h' => now()->addHour(),
            '7d' => now()->addDays(7),
            'permanent' => null,
            default => now()->addHours(24),
        };

        $allowedLanguages = ! empty($validated['allowed_languages'])
            ? array_values($validated['allowed_languages'])
            : ['en', 'ru', 'fr', 'it'];

        $pin = trim($request->input('pin') ?: ($validated['passcode'] ?? ''));
        if (empty($pin)) {
            $pin = (string) rand(100000, 999999);
        }

        $room = Room::create([
            'code' => $code,
            'title' => ! empty($validated['title']) ? trim($validated['title']) : null,
            'pin' => $pin,
            'passcode_hash' => Hash::make($pin),
            'allowed_languages' => $allowedLanguages,
            'burn_after_reading' => (bool) ($validated['burn_after_reading'] ?? false),
            'notify_admin' => (bool) ($validated['notify_admin'] ?? false),
            'expires_at' => $expiresAt,
            'created_by_ip' => $request->ip(),
            'created_by_user_id' => Auth::id(),
            'status' => 'active',
        ]);

        return redirect()->route('admin.dashboard')->with('created_room', [
            'code' => $room->code,
            'url' => route('rooms.show', ['room' => $room->code]),
            'passcode' => $pin,
            'allowed_languages' => $allowedLanguages,
        ])->with('status', "Channel [{$room->code}] established successfully.");
    }

    /**
     * Update channel details (title, PIN, allowed translations, notifications).
     */
    public function updateChannel(Request $request, string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:100'],
            'pin' => ['required', 'string', 'min:4'],
            'allowed_languages' => ['required', 'array', 'min:1'],
            'allowed_languages.*' => ['string', 'in:en,ru,fr,it'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'notify_admin' => ['nullable', 'boolean'],
        ]);

        $room->title = ! empty($validated['title']) ? trim($validated['title']) : null;
        $room->pin = trim($validated['pin']);
        $room->passcode_hash = Hash::make(trim($validated['pin']));
        $room->allowed_languages = array_values($validated['allowed_languages']);
        $room->notify_admin = (bool) ($validated['notify_admin'] ?? false);

        if (! empty($validated['status'])) {
            $room->status = $validated['status'];
        }

        $room->save();

        return redirect()->route('admin.dashboard')->with('status', "Channel [{$room->code}] updated successfully.");
    }

    /**
     * Toggle channel notification setting for admin.
     */
    public function toggleNotifications(string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);
        $room->notify_admin = ! $room->notify_admin;
        $room->save();

        $status = $room->notify_admin ? 'enabled' : 'disabled';

        return redirect()->route('admin.dashboard')->with('status', "Admin notifications {$status} for channel [{$room->code}].");
    }

    /**
     * Toggle channel status between active and archived.
     */
    public function toggleChannel(string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);
        $room->status = ($room->status === 'active') ? 'archived' : 'active';
        $room->save();

        return redirect()->route('admin.dashboard')->with('status', "Channel status updated to {$room->status}.");
    }

    /**
     * Purge and permanently erase a channel and its media files.
     */
    public function destroyChannel(string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);
        $code = $room->code;

        $room->purgeAllMedia();
        $room->messages()->delete();
        $room->accessLogs()->delete();
        $room->delete();

        return redirect()->route('admin.dashboard')->with('status', "Channel [{$code}] and all associated media have been permanently purged.");
    }

    /**
     * Generate an invite link/code or directly invite an operative to a channel.
     */
    public function createInvite(Request $request, string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        if (! empty($validated['user_id'])) {
            $targetUser = User::findOrFail($validated['user_id']);
            $room->inviteUser($targetUser, Auth::id());

            return redirect()->route('admin.dashboard')->with('status', "Direct invitation issued to Operative [{$targetUser->name}] for channel [{$room->code}].");
        }

        $invitation = \App\Models\ChannelInvitation::createForRoom(
            room: $room,
            createdByUser: Auth::user(),
            maxUses: ! empty($validated['max_uses']) ? (int) $validated['max_uses'] : null,
            expiresAt: ! empty($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null
        );

        return redirect()->route('admin.dashboard')->with('generated_invite', [
            'room_code' => $room->code,
            'code' => $invitation->code,
            'url' => route('invites.show', ['token' => $invitation->token]),
        ])->with('status', "Invitation generated for channel [{$room->code}]: Code [{$invitation->code}].");
    }

    /**
     * Format raw byte count into human-readable representation.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    /**
     * Synchronize administrator browser GPS coordinates.
     */
    public function updateGps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $lat = (float) $validated['latitude'];
        $lon = (float) $validated['longitude'];

        $geo = $this->geoLocationService->reverseGeocode($lat, $lon);

        /** @var User $adminUser */
        $adminUser = Auth::user();
        $adminUser->latitude = $lat;
        $adminUser->longitude = $lon;
        $adminUser->city = $geo['city'];
        $adminUser->country = $geo['country'];
        $adminUser->country_code = $geo['country_code'];
        $adminUser->location_synced_at = now();

        if (empty($adminUser->location) || $adminUser->location === 'Classified' || $adminUser->location === '—') {
            $adminUser->location = trim(($geo['city'] ?? '').', '.($geo['country'] ?? ''), ', ');
        }

        $adminUser->save();

        // Synchronize any existing access logs for this user
        AccessLog::where('user_id', $adminUser->id)->update([
            'latitude' => $lat,
            'longitude' => $lon,
            'city' => $geo['city'],
            'region' => $geo['region'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'latitude' => $lat,
            'longitude' => $lon,
            'city' => $geo['city'],
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'flag' => $geo['flag'],
            'location' => $adminUser->location,
            'message' => __('GPS coordinates synchronized successfully.'),
        ]);
    }
}
