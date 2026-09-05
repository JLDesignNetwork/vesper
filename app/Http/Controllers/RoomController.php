<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        if (! $room->verifyPasscode($passcode)) {
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
                'birthday' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', 'max:30'],
                'location' => ['nullable', 'string', 'max:100'],
                'bio' => ['nullable', 'string', 'max:500'],
                'email_notifications' => ['nullable', 'boolean'],
            ]);

            $prefLocale = $request->input('preferred_locale');
            $validLocale = (in_array($prefLocale, ['en', 'ru', 'fr', 'it'], true)) ? $prefLocale : null;

            $member = User::create([
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => 'member',
                'birthday' => ! empty($validated['birthday']) ? $validated['birthday'] : null,
                'gender' => ! empty($validated['gender']) ? trim($validated['gender']) : null,
                'location' => ! empty($validated['location']) ? trim($validated['location']) : null,
                'bio' => ! empty($validated['bio']) ? trim($validated['bio']) : null,
                'preferred_locale' => $validLocale,
                'email_notifications' => $request->boolean('email_notifications'),
            ]);

            Auth::login($member, true);
            $request->session()->regenerate();
            $effectiveLocale = $member->effectiveLocale();
            $request->session()->put('locale', $effectiveLocale);
            app()->setLocale($effectiveLocale);
            $alias = $member->name;
            $isAdmin = false;
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
            $isAdmin = $user->isAdmin();
        } elseif (Auth::check()) {
            $user = Auth::user();
            $alias = $user->name;
            $isAdmin = $user->isAdmin();
        } else {
            $alias = trim((string) $request->input('alias', '')) ?: 'Guest_'.strtoupper(Str::random(4));
            $isAdmin = false;
        }

        $sessionId = $request->session()->getId();

        $request->session()->put("room_clearance_{$room->id}", true);
        $request->session()->put("room_alias_{$room->id}", $alias);
        $request->session()->put("room_is_admin_{$room->id}", $isAdmin);

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

        $isAdmin = false;

        if (Auth::check()) {
            $currentUser = Auth::user();
            if ($currentUser->isAdmin()) {
                $request->session()->put("room_clearance_{$room->id}", true);
                $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                $request->session()->put("room_is_admin_{$room->id}", true);
                $hasClearance = true;
                $isAdmin = true;
            } elseif ($room->isMember($currentUser) && $currentUser->canUsePinlessEntry()) {
                $request->session()->put("room_clearance_{$room->id}", true);
                $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                $request->session()->put("room_is_admin_{$room->id}", false);
                $hasClearance = true;
                $room->touchMemberAccess($currentUser);
            } elseif ($hasClearance) {
                $request->session()->put("room_alias_{$room->id}", $currentUser->name);
                $request->session()->put("room_is_admin_{$room->id}", false);
            }
        } else {
            $isAdmin = (bool) $request->session()->get("room_is_admin_{$room->id}", false);
        }

        if (! $hasClearance) {
            return view('channel_entry', [
                'targetRoomCode' => $room->code,
                'roomTitle' => $room->title,
                'authMember' => (Auth::check() && Auth::user()->isMember()) ? Auth::user() : null,
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

        return view('room', [
            'room' => $room,
            'alias' => $alias,
            'isAdmin' => $isAdmin,
            'sessionId' => $sessionId,
            'clientIp' => $clientIp,
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
        $room->purgeAllMedia();
        $room->messages()->delete();
        $room->accessLogs()->delete();
        $room->delete();

        $request->session()->forget("room_clearance_{$room->id}");
        $request->session()->forget("room_alias_{$room->id}");
        $request->session()->forget("room_is_admin_{$room->id}");

        if (Auth::check()) {
            return redirect()->route('admin.dashboard')
                ->with('status', 'Channel and all associated media have been permanently purged.');
        }

        return redirect()->route('portal')
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
            'is_admin' => ($viewer && $viewer->isAdmin()),
            'preferred_locale' => ($viewer && ($viewer->isAdmin() || $viewer->id === $user->id)) ? $user->preferred_locale : null,
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
}

