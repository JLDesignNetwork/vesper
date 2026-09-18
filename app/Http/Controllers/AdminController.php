<?php

namespace App\Http\Controllers;

use App\Mail\ChannelInvitationNotification;
use App\Models\AccessLog;
use App\Models\ChannelInvitation;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
     * Display the Admin Overview Dashboard.
     */
    public function index(): View
    {
        $dashboardData = $this->dashboardService->getDashboardData();

        return view('admin.dashboard', array_merge([
            'adminUser' => Auth::user(),
        ], $dashboardData));
    }

    /**
     * Display the Encrypted Channels Management page.
     */
    public function channels(): View
    {
        $channelsData = $this->dashboardService->getChannelsData();

        return view('admin.channels.index', array_merge([
            'adminUser' => Auth::user(),
        ], $channelsData));
    }

    /**
     * Display the Registered Members Directory page.
     */
    public function members(): View
    {
        $membersData = $this->dashboardService->getMembersData();

        return view('admin.members.index', array_merge([
            'adminUser' => Auth::user(),
        ], $membersData));
    }

    /**
     * Compatibility redirect for operatives URL.
     */
    public function operatives(): RedirectResponse
    {
        return redirect()->route('admin.members.index');
    }

    /**
     * Display the Global Intelligence & Satellite Radar Map page.
     */
    public function intel(): View
    {
        $intelData = $this->dashboardService->getIntelData();

        return view('admin.intel.index', array_merge([
            'adminUser' => Auth::user(),
        ], $intelData));
    }

    /**
     * Display the Transmission & Connection Logs page.
     */
    public function logs(): View
    {
        $logsData = $this->dashboardService->getLogsData();

        return view('admin.logs.index', array_merge([
            'adminUser' => Auth::user(),
        ], $logsData));
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
            'allowed_languages.*' => ['string', Rule::in(LanguageService::codes())],
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

        return redirect()->route('admin.channels.index')->with('created_room', [
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
            'allowed_languages.*' => ['string', Rule::in(LanguageService::codes())],
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

        return redirect()->route('admin.channels.index')->with('status', "Channel [{$room->code}] updated successfully.");
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

        return redirect()->route('admin.channels.index')->with('status', "Admin notifications {$status} for channel [{$room->code}].");
    }

    /**
     * Toggle channel status between active and archived.
     */
    public function toggleChannel(string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);
        $room->status = ($room->status === 'active') ? 'archived' : 'active';
        $room->save();

        return redirect()->route('admin.channels.index')->with('status', "Channel status updated to {$room->status}.");
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

        return redirect()->route('admin.channels.index')->with('status', "Channel [{$code}] and all associated media have been permanently purged.");
    }

    /**
     * Generate an invite link/code or directly invite an operative to a channel.
     */
    public function createInvite(Request $request, string $id): RedirectResponse
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'send_email' => ['nullable', 'boolean'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $targetUser = null;
        if (! empty($validated['user_id'])) {
            $targetUser = User::find($validated['user_id']);
        } elseif (! empty($validated['email'])) {
            $email = strtolower(trim($validated['email']));
            $targetUser = User::where('email', $email)->first();
            if (! $targetUser) {
                $targetUser = User::create([
                    'name' => ucfirst(explode('@', $email)[0]),
                    'email' => $email,
                    'password' => Hash::make(Str::random(24)),
                    'role' => 'member',
                    'preferred_locale' => app()->getLocale(),
                ]);
            }
        }

        if ($targetUser) {
            $room->inviteUser($targetUser, Auth::id());

            if ($request->boolean('send_email', true) && ! empty($targetUser->email) && filter_var($targetUser->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($targetUser->email)->send(new ChannelInvitationNotification(
                        recipient: $targetUser,
                        room: $room,
                        invitationUrl: route('rooms.show', ['room' => $room->code]),
                        invitationCode: $room->code,
                        inviterName: Auth::user()->name
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Failed to dispatch channel invitation notification: {$e->getMessage()}");
                }
            }

            return redirect()->route('admin.channels.index')->with(
                'status',
                __('Direct invitation issued to Member [:name] for channel [:code] (Access PIN: :pin).', [
                    'name' => $targetUser->name,
                    'code' => $room->code,
                    'pin' => $room->pin,
                ])
            );
        }

        $invitation = ChannelInvitation::createForRoom(
            room: $room,
            createdByUser: Auth::user(),
            maxUses: ! empty($validated['max_uses']) ? (int) $validated['max_uses'] : null,
            expiresAt: ! empty($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null
        );

        return redirect()->route('admin.channels.index')->with('generated_invite', [
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
