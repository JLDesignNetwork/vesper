<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Models\Room;
use App\Services\ChannelAccessService;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Member-facing channel management: the public directory, creating a channel,
 * and the creator's settings.
 */
class ChannelController extends Controller
{
    public function __construct(
        protected ChannelAccessService $channelAccess,
        protected GeoLocationService $geoLocationService
    ) {}

    /**
     * List public channels. Signed-out visitors see the same list with limited details
     * (title, description, type and member count) and are asked to sign in to join.
     */
    public function directory(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $channels = Room::query()
            ->whereIn('type', array_map(fn (ChannelType $type) => $type->value, [ChannelType::PublicSecured, ChannelType::PublicFreeEntry]))
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('code', 'like', '%'.strtoupper($search).'%');
            }))
            ->withCount('members')
            ->orderByDesc('members_count')
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('channels.directory', [
            'channels' => $channels,
            'search' => $search,
            'viewer' => $request->user(),
        ]);
    }

    /**
     * Create a channel owned by the signed-in member and open it for them.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate($this->rules($request, null));
        $type = ChannelType::from($validated['type']);

        $room = Room::create([
            'code' => $this->uniqueCode(),
            'title' => trim($validated['title']),
            'description' => $this->cleanDescription($validated['description'] ?? null),
            'type' => $type,
            'pin' => $this->usesPin($type) ? $validated['pin'] : null,
            'allowed_languages' => $validated['allowed_languages'] ?? null,
            'expires_at' => $this->expiryFrom($validated['expiration'] ?? 'permanent'),
            'created_by_ip' => $this->geoLocationService->getClientIp($request),
            'created_by_user_id' => $user->id,
            'status' => 'active',
        ]);

        $room->addMember($user, 'owner', null, $user->name);
        $this->channelAccess->grantClearance($request, $room, $user->name);

        return redirect()->route('rooms.show', ['room' => $room->code])
            ->with('status', __('Channel :title created. Its code is :code.', ['title' => $room->title, 'code' => $room->code]));
    }

    /**
     * Turn pin-free entry (2FA or passkey instead of the PIN) on or off for one of the
     * member's channels. `pra` always needs a passkey check and has no switch.
     */
    public function updatePinlessEntry(Request $request, Room $room): RedirectResponse
    {
        $user = $request->user();
        abort_unless($room->isMember($user), 404);

        if ($room->type->isPasskeyOnly() || ! $room->isSecured()) {
            return back()->withErrors(['channel' => __('This channel has no PIN, so there is nothing to switch.')]);
        }

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        app(ChannelAccessService::class)->setPinlessEntry($user, $room, (bool) $validated['enabled']);

        return back()->with('status', $validated['enabled']
            ? __('Pin-free entry is on for :title.', ['title' => $room->title ?: $room->code])
            : __(':title now always asks for its PIN.', ['title' => $room->title ?: $room->code]));
    }

    /**
     * Save the creator's changes to a channel's settings.
     */
    public function update(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('update', $room);

        $validated = $request->validate($this->rules($request, $room));
        $type = $room->is_system ? $room->type : ChannelType::from($validated['type']);

        $room->title = trim($validated['title']);
        $room->description = $this->cleanDescription($validated['description'] ?? null);
        $room->type = $type;

        if (array_key_exists('allowed_languages', $validated)) {
            $room->allowed_languages = $validated['allowed_languages'];
        }

        if (! empty($validated['pin'])) {
            $room->pin = $validated['pin'];
        }

        $room->save();

        return redirect()->route('profile.show')
            ->with('status', __('Settings saved for :title.', ['title' => $room->title]));
    }

    /**
     * Validation rules shared by create and update.
     *
     * A PIN is required for a secured channel unless the channel already has one.
     * Members may choose the four member types; only Admin I may choose admin types.
     *
     * @return array<string, mixed>
     */
    protected function rules(Request $request, ?Room $room): array
    {
        $allowedTypes = $request->user()->hasRank(Rank::AdminOne)
            ? ChannelType::cases()
            : ChannelType::memberCreatable();

        $requestedType = $room?->is_system ? $room->type : ChannelType::tryFrom((string) $request->input('type'));
        $needsPin = $requestedType !== null && $this->usesPin($requestedType) && empty($room?->passcode_hash);

        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => [$room?->is_system ? 'nullable' : 'required', Rule::in(array_map(fn (ChannelType $type) => $type->value, $allowedTypes))],
            'pin' => [$needsPin ? 'required' : 'nullable', 'string', 'min:4', 'max:64'],
            'allowed_languages' => ['nullable', 'array'],
            'allowed_languages.*' => ['string', Rule::in(LanguageService::codes())],
            'expiration' => [$room ? 'prohibited' : 'nullable', 'string', 'in:1h,24h,7d,permanent'],
        ];
    }

    /**
     * Whether the channel type is opened with a PIN. Passkey-only channels (pra) never are.
     */
    protected function usesPin(ChannelType $type): bool
    {
        return $type->isSecured() && ! $type->isPasskeyOnly();
    }

    /**
     * Generate an unused channel code such as K7QX-4821.
     */
    protected function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(4)).'-'.random_int(1000, 9999);
        } while (Room::where('code', $code)->exists());

        return $code;
    }

    /**
     * Convert an expiration choice into a timestamp (null means it never expires).
     */
    protected function expiryFrom(string $choice): ?\DateTimeInterface
    {
        return match ($choice) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            default => null,
        };
    }

    /**
     * Trim a description and store an empty one as null.
     */
    protected function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description === '' ? null : $description;
    }
}
