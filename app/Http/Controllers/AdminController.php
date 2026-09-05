<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminDashboardService;
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
        public MediaStorageService $mediaStorageService,
        public AdminDashboardService $dashboardService
    ) {}

    /**
     * Display the Admin Dashboard with stats, channels, visitor logs, and map.
     */
    public function index(): View
    {
        $dashboardData = $this->dashboardService->getDashboardData();

        return view('admin.dashboard', array_merge([
            'adminUser' => Auth::user(),
        ], $dashboardData));
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
        return $this->dashboardService->formatBytes($bytes);
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
