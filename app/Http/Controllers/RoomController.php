<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AccessLog;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\RankService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        public GeoLocationService $geoLocationService
    ) {}

    /**
     * Display the portal entrance.
     */
    public function index(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->homeRoute());
        }

        return redirect()->route('login');
    }

    /**
     * Store a newly created secret room.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-_]+$/', 'unique:rooms,code'],
            'title' => ['nullable', 'string', 'max:80'],
            'passcode' => ['required', 'string', 'min:4', 'max:64'],
            'alias' => ['nullable', 'string', 'max:30'],
            'burn_after_reading' => ['nullable', 'boolean'],
            'expires_in_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);

        $code = ! empty($validated['code'])
            ? strtoupper($validated['code'])
            : 'CIPHER-'.strtoupper(Str::random(6));

        $clientIp = $this->geoLocationService->getClientIp($request);

        $expiresAt = null;
        if (! empty($validated['expires_in_hours']) && (int) $validated['expires_in_hours'] > 0) {
            $expiresAt = now()->addHours((int) $validated['expires_in_hours']);
        }

        $room = Room::create([
            'code' => $code,
            'title' => $validated['title'] ?? null,
            'passcode_hash' => Hash::make($validated['passcode']),
            'burn_after_reading' => (bool) ($validated['burn_after_reading'] ?? false),
            'expires_at' => $expiresAt,
            'created_by_ip' => $clientIp,
            'created_by_user_id' => Auth::id(),
            'status' => 'active',
        ]);

        $alias = trim($validated['alias'] ?? '') ?: (Auth::check() ? Auth::user()->name : 'Commander');
        $sessionId = $request->session()->getId();

        if (Auth::check()) {
            $room->addMember(Auth::user(), 'owner', null, $alias);
        }

        $request->session()->put("room_clearance_{$room->id}", true);
        $request->session()->put("room_alias_{$room->id}", $alias);

        $geo = $this->geoLocationService->locate($clientIp);
        $user = Auth::user();
        $userId = $user?->id;
        $lat = ($user && $user->hasGps()) ? $user->latitude : $geo['latitude'];
        $lon = ($user && $user->hasGps()) ? $user->longitude : $geo['longitude'];
        $city = ($user && $user->city) ? $user->city : $geo['city'];
        $country = ($user && $user->country) ? $user->country : $geo['country'];
        $countryCode = ($user && $user->country_code) ? $user->country_code : $geo['country_code'];

        AccessLog::create([
            'room_id' => $room->id,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'alias' => $alias,
            'ip_address' => $clientIp,
            'city' => $city,
            'region' => $geo['region'],
            'country' => $country,
            'country_code' => $countryCode,
            'latitude' => $lat,
            'longitude' => $lon,
            'isp' => $geo['isp'],
            'user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
        ]);

        return redirect()->route('rooms.show', ['room' => $room->code])
            ->with('status', 'Secure channel established. Welcome, '.$alias.'.');
    }

    /**
     * Verify passkey and grant room clearance.
     */
    public function verify(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $clientIp = $this->geoLocationService->getClientIp($request);
        $rateLimitKey = 'room_auth_'.$clientIp.'_'.$code;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'passcode' => "Lockout protocol active. Too many failed attempts. Retry in {$seconds} seconds.",
            ]);
        }

        $room = Room::where('code', $code)->where('status', 'active')->first();

        if (! $room || $room->isExpired()) {
            return redirect()->route('portal')
                ->withErrors(['code' => 'Channel does not exist, has expired, or was purged.']);
        }

        $passcode = (string) $request->input('passcode', '');
        $isDuress = false;

        if ($room->verifyDuressPasscode($passcode)) {
            $isDuress = true;
        } elseif (! $room->verifyPasscode($passcode)) {
            RateLimiter::hit($rateLimitKey, 120);

            return back()->withErrors([
                'passcode' => 'Invalid Secret Passcode. Transmission rejected.',
            ]);
        }

        RateLimiter::clear($rateLimitKey);

        $mode = $request->input('mode', 'guest');

        if ($mode === 'register') {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:6'],
                'preferred_locale' => ['nullable', 'string', Rule::in(LanguageService::codes())],
                'birthday' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', 'max:30'],
                'location' => ['nullable', 'string', 'max:100'],
                'bio' => ['nullable', 'string', 'max:500'],
                'email_notifications' => ['nullable', 'boolean'],
            ]);

            $prefLocale = $request->input('preferred_locale') ?: session('locale', 'en');
            $validLocale = LanguageService::isValid($prefLocale) ? $prefLocale : 'en';

            $member = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'birthday' => ! empty($validated['birthday']) ? $validated['birthday'] : null,
                'gender' => ! empty($validated['gender']) ? trim($validated['gender']) : null,
                'location' => ! empty($validated['location']) ? trim($validated['location']) : null,
                'bio' => ! empty($validated['bio']) ? trim($validated['bio']) : null,
                'preferred_locale' => $validLocale,
                'email_notifications' => $request->boolean('email_notifications'),
            ]);

            $member->sendEmailVerificationNotification();

            Auth::login($member, true);
            $request->session()->regenerate();
            $effectiveLocale = $member->preferred_locale;
            $request->session()->put('locale', $effectiveLocale);
            app()->setLocale($effectiveLocale);
            $alias = $member->name;
        } elseif ($mode === 'login') {
            $validated = $request->validate([
                'login' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);

            $loginInput = trim($validated['login']);
            $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

            if (! Auth::attempt([$field => $loginInput, 'password' => $validated['password']], true)) {
                return back()->withErrors([
                    'login' => __('Invalid credentials provided.'),
                ]);
            }

            $request->session()->regenerate();
            $user = Auth::user();
            $effectiveLocale = $user->effectiveLocale();
            $request->session()->put('locale', $effectiveLocale);
            app()->setLocale($effectiveLocale);
            $alias = $user->name;
        } elseif (Auth::check()) {
            $user = Auth::user();
            $alias = $user->name;
        } else {
            $alias = trim((string) $request->input('alias', '')) ?: 'Guest_'.strtoupper(Str::random(4));
        }

        $sessionId = $request->session()->getId();

        $request->session()->put("room_clearance_{$room->id}", true);
        $request->session()->put("room_alias_{$room->id}", $alias);
        $request->session()->put("room_is_duress_{$room->id}", $isDuress);

        $geo = $this->geoLocationService->locate($clientIp);
        $user = Auth::user();
        $userId = $user?->id;
        $lat = ($user && $user->hasGps()) ? $user->latitude : $geo['latitude'];
        $lon = ($user && $user->hasGps()) ? $user->longitude : $geo['longitude'];
        $city = ($user && $user->city) ? $user->city : $geo['city'];
        $country = ($user && $user->country) ? $user->country : $geo['country'];
        $countryCode = ($user && $user->country_code) ? $user->country_code : $geo['country_code'];

        AccessLog::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $userId,
                'alias' => $isDuress ? $alias.' [DURESS_TRIGGER]' : $alias,
                'ip_address' => $clientIp,
                'city' => $city,
                'region' => $geo['region'],
                'country' => $country,
                'country_code' => $countryCode,
                'latitude' => $lat,
                'longitude' => $lon,
                'isp' => $geo['isp'],
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );

        if ($user) {
            $room->addMember($user, 'member', null, $alias);
        }

        return redirect()->route('rooms.show', ['room' => $room->code]);
    }

    /**
     * Display the room or prompt for passkey if clearance is missing.
     */
    public function show(Request $request, string $code): View|RedirectResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->where('status', 'active')->first();

        if (! $room) {
            return redirect()->route('portal')
                ->withErrors(['code' => 'Requested channel does not exist or has been terminated.']);
        }

        if ($room->isExpired()) {
            $room->purgeAllMedia();
            $room->update(['status' => 'destroyed']);

            return redirect()->route('portal')
                ->withErrors(['code' => 'This secure channel reached its expiration timer and was self-destructed.']);
        }

        $hasClearance = $request->session()->get("room_clearance_{$room->id}", false);

        if (Auth::check()) {
            $currentUser = Auth::user();
            $isMember = $room->isMember($currentUser);

            if ($currentUser->can('overrideAccess', $room)) {
                $request->session()->put("room_clearance_{$room->id}", true);
                $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                $hasClearance = true;

                if (! $isMember) {
                    AdminAuditLog::record(AuditAction::ChannelAccessOverride, actor: $currentUser, room: $room);
                }
            } elseif ($isMember) {
                if ($currentUser->canUsePinlessEntry()) {
                    $request->session()->put("room_clearance_{$room->id}", true);
                    $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                    $hasClearance = true;
                    $room->touchMemberAccess($currentUser);
                } elseif ($hasClearance) {
                    $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                }
            } elseif ($hasClearance) {
                $request->session()->put("room_alias_{$room->id}", $currentUser->name);
            } else {
                return redirect()->route('profile.show')
                    ->withErrors(['code' => __('Channel not found.')]);
            }
        }

        if (! $hasClearance) {
            return view('channel_entry', [
                'targetRoomCode' => $room->code,
                'roomTitle' => $room->title,
                'authMember' => Auth::user(),
            ]);
        }

        $alias = $request->session()->get("room_alias_{$room->id}", 'Guest');
        $sessionId = $request->session()->getId();
        $clientIp = $this->geoLocationService->getClientIp($request);

        $geo = $this->geoLocationService->locate($clientIp);
        $user = Auth::user();
        $userId = $user?->id;
        $lat = ($user && $user->hasGps()) ? $user->latitude : $geo['latitude'];
        $lon = ($user && $user->hasGps()) ? $user->longitude : $geo['longitude'];
        $city = ($user && $user->city) ? $user->city : $geo['city'];
        $country = ($user && $user->country) ? $user->country : $geo['country'];
        $countryCode = ($user && $user->country_code) ? $user->country_code : $geo['country_code'];

        AccessLog::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $userId,
                'alias' => $alias,
                'ip_address' => $clientIp,
                'city' => $city,
                'region' => $geo['region'],
                'country' => $country,
                'country_code' => $countryCode,
                'latitude' => $lat,
                'longitude' => $lon,
                'isp' => $geo['isp'],
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );

        $userPreferredLocale = $user?->preferred_locale ?: session('locale', config('app.locale', 'en'));

        return view('room', [
            'room' => $room,
            'alias' => $alias,
            'isAdmin' => (bool) $user?->displaysAsAdmin(),
            'canPinMessages' => (bool) $user?->can('pinMessages', $room),
            'canViewRadar' => (bool) $user?->can('viewGeolocation'),
            'canDestroyChannel' => (bool) $user?->can('destroy', $room),
            'sessionId' => $sessionId,
            'clientIp' => $clientIp,
            'userPreferredLocale' => $userPreferredLocale,
        ]);
    }

    /**
     * Instantly nuke, purge, and destroy the room and all media.
     */
    public function nuke(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room) {
            return redirect()->route('portal');
        }

        $hasClearance = $request->session()->get("room_clearance_{$room->id}", false);
        if (! $hasClearance) {
            abort(403, 'Unauthorized. Clearance credentials required to execute self-destruct.');
        }

        if (! $request->user()?->can('destroy', $room)) {
            abort(403, 'Unauthorized. Only the channel creator or Admin I can purge this channel.');
        }

        $room->purgeAllMedia();
        $room->messages()->delete();
        $room->accessLogs()->delete();
        $room->delete();

        $request->session()->forget("room_clearance_{$room->id}");
        $request->session()->forget("room_alias_{$room->id}");

        app(RankService::class)->recomputeCreatorOf($room);

        return redirect()->to($request->user()->homeRoute())
            ->with('status', 'Channel and all associated media have been permanently purged.');
    }

    /**
     * Lock the session and revoke clearance.
     */
    public function lock(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if ($room) {
            $request->session()->forget("room_clearance_{$room->id}");
            $request->session()->forget("room_alias_{$room->id}");
        }

        return redirect()->route('portal')
            ->with('status', 'Channel locked. Authentication revoked.');
    }

    /**
     * Fetch live member profile data for in-chat member details card.
     */
    public function memberProfile(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || ! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $name = trim((string) $request->query('name', ''));
        if ($name === '') {
            return response()->json(['found' => false]);
        }

        $user = User::where('name', $name)->first();

        if (! $user) {
            return response()->json([
                'found' => false,
                'name' => $name,
            ]);
        }

        $viewer = Auth::user();

        return response()->json([
            'found' => true,
            'name' => $user->name,
            'avatar_url' => $user->avatarUrl(),
            'age' => $user->ageForViewer($viewer),
            'birthday' => $user->birthdayForViewer($viewer),
            'gender' => $user->gender,
            'location' => $user->locationForViewer($viewer),
            'bio' => $user->bioForViewer($viewer),
            'is_self' => ($viewer && $viewer->id === $user->id),
            'is_admin' => (bool) $viewer?->can('viewHiddenFields', $user),
            'preferred_locale' => $user->hiddenFieldsVisibleTo($viewer) ? $user->preferred_locale : null,
            'effective_locale' => $user->effectiveLocale(),
            'location_locale' => $user->resolveLocationLocale(),
            'privacy' => [
                'age_hidden' => (bool) $user->hide_age,
                'birthday_hidden' => (bool) $user->hide_birthday,
                'location_hidden' => (bool) $user->hide_location,
                'bio_hidden' => (bool) $user->hide_bio,
            ],
        ]);
    }

    /**
     * Toggle pinning a message to the channel top banner.
     */
    public function togglePin(Request $request, string $code, int|string $messageId): JsonResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->first();

        if (! $room || $room->status !== 'active') {
            return response()->json(['error' => 'Channel terminated'], 410);
        }

        if (! $request->session()->get("room_clearance_{$room->id}", false)) {
            return response()->json(['error' => 'Clearance required'], 403);
        }

        if (! $request->user()?->can('pinMessages', $room)) {
            return response()->json(['error' => 'Unauthorized. Only channel owners or admins can pin messages.'], 403);
        }

        $message = Message::where('room_id', $room->id)->find($messageId);
        if (! $message) {
            return response()->json(['error' => 'Message not found'], 404);
        }

        if ($room->pinned_message_id == $message->id) {
            $room->update(['pinned_message_id' => null]);
            $isPinned = false;
        } else {
            $room->update(['pinned_message_id' => $message->id]);
            $isPinned = true;
        }

        return response()->json([
            'success' => true,
            'is_pinned' => $isPinned,
            'pinned_message_id' => $room->pinned_message_id,
        ]);
    }
}
