<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\ChannelEntry;
use App\Models\AccessLog;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\MessagePin;
use App\Models\Room;
use App\Models\User;
use App\Services\BlockService;
use App\Services\ChannelAccessService;
use App\Services\GeoLocationService;
use App\Services\RankService;
use App\Services\UserTagService;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        public GeoLocationService $geoLocationService,
        protected ChannelAccessService $channelAccess,
        protected BlockService $blocks
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
     * Check the member's PIN (or 2FA / passkey eligibility) and open the channel.
     * Private channels never reveal whether they exist to someone who may not enter.
     */
    public function verify(Request $request, string $code): RedirectResponse
    {
        $code = strtoupper(trim($code));
        $user = $request->user();
        $clientIp = $this->geoLocationService->getClientIp($request);
        $rateLimitKey = 'room_auth_'.$clientIp.'_'.$code;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'passcode' => "Lockout protocol active. Too many failed attempts. Retry in {$seconds} seconds.",
            ]);
        }

        $room = Room::where('code', $code)->where('status', 'active')->first();
        $entry = $room ? $this->channelAccess->entryFor($user, $room) : ChannelEntry::Denied;

        if ($entry === ChannelEntry::Denied) {
            RateLimiter::hit($rateLimitKey, 120);

            return redirect()->route('profile.show')
                ->withErrors(['code' => __('Channel does not exist, has expired, or was purged.')]);
        }

        if ($entry === ChannelEntry::PasskeyRequired) {
            return back()->withErrors([
                'passcode' => __('This channel opens only with a biometric passkey. Enroll one in your profile first.'),
            ]);
        }

        if ($entry === ChannelEntry::PasskeyAssertionRequired) {
            return back()->withErrors([
                'passcode' => __('This channel opens only with a passkey check. Use the passkey button to continue.'),
            ]);
        }

        $isDuress = false;

        if ($entry === ChannelEntry::PinRequired) {
            $passcode = (string) $request->input('passcode', '');

            if ($room->verifyDuressPasscode($passcode)) {
                $isDuress = true;
            } elseif (! $room->verifyPasscode($passcode)) {
                RateLimiter::hit($rateLimitKey, 120);

                return back()->withErrors([
                    'passcode' => 'Invalid Secret Passcode. Transmission rejected.',
                ]);
            }
        }

        RateLimiter::clear($rateLimitKey);

        $this->openChannel($request, $room, $user);
        $request->session()->put("room_is_duress_{$room->id}", $isDuress);
        $this->recordAccess($request, $room, $isDuress ? $user->name.' [DURESS_TRIGGER]' : $user->name);

        return redirect()->route('rooms.show', ['room' => $room->code]);
    }

    /**
     * Display the channel, or the entry gate when the member must present a PIN first.
     */
    public function show(Request $request, string $code): View|RedirectResponse
    {
        $code = strtoupper(trim($code));
        $room = Room::where('code', $code)->where('status', 'active')->first();

        if (! $room) {
            return redirect()->route('profile.show')
                ->withErrors(['code' => __('Requested channel does not exist or has been terminated.')]);
        }

        if ($room->isExpired()) {
            $room->purgeAllMedia();
            $room->update(['status' => 'destroyed']);
            app(RankService::class)->recomputeCreatorOf($room);

            return redirect()->route('profile.show')
                ->withErrors(['code' => __('This secure channel reached its expiration timer and was self-destructed.')]);
        }

        $user = $request->user();
        $hasClearance = $this->channelAccess->hasClearance($request, $room)
            && $this->channelAccess->mayContinue($user, $room);

        if (! $hasClearance) {
            $entry = $this->channelAccess->entryFor($user, $room);

            if ($entry === ChannelEntry::Denied) {
                $this->channelAccess->revokeClearance($request, $room);

                return redirect()->route('profile.show')
                    ->withErrors(['code' => __('Channel not found.')]);
            }

            if ($entry !== ChannelEntry::Granted) {
                return view('channel_entry', [
                    'targetRoomCode' => $room->code,
                    'roomTitle' => $room->title,
                    'authMember' => $user,
                    'requiresPasskey' => $entry === ChannelEntry::PasskeyRequired,
                    'requiresPasskeyCheck' => $entry === ChannelEntry::PasskeyAssertionRequired,
                    'offersPasskey' => $this->channelAccess->allowsPasskeyEntry($user, $room),
                ]);
            }

            $this->openChannel($request, $room, $user);
        }

        if ($user->can('overrideAccess', $room) && ! $room->isMember($user)) {
            AdminAuditLog::record(AuditAction::ChannelAccessOverride, actor: $user, room: $room);
        }

        $alias = $request->session()->get("room_alias_{$room->id}", $user->name);
        $this->recordAccess($request, $room, $alias);

        $userPreferredLocale = $user->preferred_locale ?: session('locale', config('app.locale', 'en'));

        return view('room', [
            'room' => $room,
            'alias' => $alias,
            'isAdmin' => $user->displaysAsAdmin(),
            'canPinMessages' => $user->can('pinMessages', $room),
            'canViewRadar' => $user->can('viewGeolocation'),
            'canDestroyChannel' => $user->can('destroy', $room),
            'isRestricted' => $user->isRestricted(),
            'removedMembers' => $user->can('kickMembers', $room) ? $room->kickedMembers()->get(['users.id', 'users.name', 'users.tag']) : collect(),
            'clearanceIdleSeconds' => $room->isSecured() ? ChannelAccessService::CLEARANCE_IDLE_MINUTES * 60 : null,
            'sessionId' => $request->session()->getId(),
            'clientIp' => $this->geoLocationService->getClientIp($request),
            'userPreferredLocale' => $userPreferredLocale,
        ]);
    }

    /**
     * Start a passkey check for opening the channel (pra, or PIN-secured channels for
     * members eligible for passkey entry). Only the member's own passkeys are allowed.
     */
    public function passkeyOptions(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper(trim($code)))->where('status', 'active')->first();
        $user = $request->user();

        if (! $room || ! $this->channelAccess->allowsPasskeyEntry($user, $room)) {
            return response()->json(['error' => __('A passkey cannot be used to open this channel.')], 403);
        }

        return response()->json(app(WebAuthnService::class)->generateLoginOptions($user, $this->passkeyChallengeKey($room)));
    }

    /**
     * Verify the passkey check and open the channel. A fresh check is needed every time
     * the channel is opened, because clearance on secured channels lapses when idle.
     */
    public function passkeyVerify(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', strtoupper(trim($code)))->where('status', 'active')->first();
        $user = $request->user();

        if (! $room || ! $this->channelAccess->allowsPasskeyEntry($user, $room)) {
            return response()->json(['error' => __('A passkey cannot be used to open this channel.')], 403);
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:1024'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        try {
            app(WebAuthnService::class)->verifyAssertion($validated, $this->passkeyChallengeKey($room), $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $this->openChannel($request, $room, $user);
        $this->recordAccess($request, $room, $user->name);

        return response()->json(['success' => true, 'redirect' => route('rooms.show', ['room' => $room->code])]);
    }

    protected function passkeyChallengeKey(Room $room): string
    {
        return "webauthn_channel_challenge_{$room->id}";
    }

    /**
     * Keep a secured channel open while the member is actively using it. The clearance
     * middleware extends clearance on this request; polling does not.
     */
    public function heartbeat(): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    /**
     * Grant clearance, enroll public-channel joiners and the creator as members, and
     * record the member's latest access. Admin I opening a channel is not membership.
     */
    protected function openChannel(Request $request, Room $room, User $user): void
    {
        $this->channelAccess->grantClearance($request, $room, $user->name);

        if ($room->isMember($user)) {
            $room->touchMemberAccess($user);
        } elseif ($room->isPublic() || $room->isCreatedBy($user)) {
            $room->addMember($user, $room->isCreatedBy($user) ? 'owner' : 'member', null, $user->name);
        }
    }

    /**
     * Record (or refresh) this session's access log entry for the channel.
     */
    protected function recordAccess(Request $request, Room $room, string $alias): void
    {
        $user = $request->user();
        $clientIp = $this->geoLocationService->getClientIp($request);
        $geo = $this->geoLocationService->locate($clientIp);

        AccessLog::updateOrCreate(
            [
                'room_id' => $room->id,
                'session_id' => $request->session()->getId(),
            ],
            [
                'user_id' => $user?->id,
                'alias' => $alias,
                'ip_address' => $clientIp,
                'city' => $user?->city ?: $geo['city'],
                'region' => $geo['region'],
                'country' => $user?->country ?: $geo['country'],
                'country_code' => $user?->country_code ?: $geo['country_code'],
                'latitude' => ($user && $user->hasGps()) ? $user->latitude : $geo['latitude'],
                'longitude' => ($user && $user->hasGps()) ? $user->longitude : $geo['longitude'],
                'isp' => $geo['isp'],
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
            ]
        );
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

        $this->channelAccess->revokeClearance($request, $room);

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
            $this->channelAccess->revokeClearance($request, $room);
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

        $tag = app(UserTagService::class)->normalize((string) $request->query('tag', ''));
        $name = trim((string) $request->query('name', ''));

        if ($tag !== '') {
            // A highlighted @tag opens the card only for members of this channel.
            $user = $room->members()->where('users.tag', $tag)->first()
                ?? ($room->creator?->tag === $tag ? $room->creator : null);
            $name = $tag;
        } elseif ($name !== '') {
            // Names are not unique and cards must not reveal people outside the channel:
            // look the name up among the channel's members, creator and message authors only.
            $user = User::where('name', $name)
                ->where(fn ($query) => $query
                    ->whereIn('id', DB::table('room_user')->where('room_id', $room->id)->where('status', 'active')->select('user_id'))
                    ->orWhere('id', $room->created_by_user_id)
                    ->orWhereIn('id', Message::where('room_id', $room->id)->whereNotNull('user_id')->select('user_id')))
                ->first();
        } else {
            return response()->json(['found' => false]);
        }

        $viewer = Auth::user();

        if (! $user || $this->blocks->isBlockedBetween($viewer, $user, $room)) {
            return response()->json([
                'found' => false,
                'name' => $name,
            ]);
        }

        return response()->json([
            'found' => true,
            'name' => $user->name,
            'tag' => $user->tag,
            'avatar_url' => $user->avatarUrl(),
            'age' => $user->ageForViewer($viewer),
            'birthday' => $user->birthdayForViewer($viewer),
            'gender' => $user->gender,
            'location' => $user->locationForViewer($viewer),
            'bio' => $user->bioForViewer($viewer),
            'is_self' => ($viewer && $viewer->id === $user->id),
            'is_admin' => (bool) $viewer?->can('viewHiddenFields', $user),
            'shows_as_admin' => $user->displaysAsAdmin(),
            'can_kick' => ! $user->is($viewer) && (bool) $viewer?->can('kickMembers', $room) && $room->isMember($user),
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

        $existingPin = MessagePin::where('room_id', $room->id)->where('message_id', $message->id)->first();

        if ($existingPin) {
            $existingPin->delete();
            $isPinned = false;
        } else {
            if ($message->hasSelfDestructed()) {
                return response()->json(['error' => __('This message has already self-destructed and cannot be pinned.')], 422);
            }

            $pinned = DB::transaction(function () use ($room, $message, $request): bool {
                if (MessagePin::where('room_id', $room->id)->lockForUpdate()->count() >= MessagePin::MAX_PER_CHANNEL) {
                    return false;
                }

                MessagePin::create([
                    'room_id' => $room->id,
                    'message_id' => $message->id,
                    'pinned_by_user_id' => $request->user()->id,
                ]);

                return true;
            });

            if (! $pinned) {
                return response()->json([
                    'error' => __('This channel already has :max pinned messages. Unpin one first.', ['max' => MessagePin::MAX_PER_CHANNEL]),
                ], 422);
            }

            $isPinned = true;
        }

        return response()->json([
            'success' => true,
            'is_pinned' => $isPinned,
            'pinned_message_id' => $isPinned ? $message->id : null,
            'pinned_messages' => $room->pinnedTray($this->blocks->hiddenUserIdsFor($request->user(), $room)),
        ]);
    }
}
