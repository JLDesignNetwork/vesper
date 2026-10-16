<?php

use App\Enums\ChannelEntry;
use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Models\Room;
use App\Models\User;
use App\Services\ChannelAccessService;
use Illuminate\Support\Str;

/**
 * Create a channel of the given type with a known PIN.
 */
function channelOfType(ChannelType $type, array $attributes = []): Room
{
    return Room::create(array_merge([
        'code' => 'TYPE-'.strtoupper(Str::random(5)),
        'type' => $type,
        'pin' => '4321',
        'status' => 'active',
        'created_by_user_id' => User::factory()->create()->id,
    ], $attributes));
}

function withTwoFactor(User $user): User
{
    $user->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

/* -------------------------------------------------------------
 * Entry decisions
 * ------------------------------------------------------------- */

test('a member opening a channel is told what they must present', function (ChannelType $type, bool $hasTwoFactor, ChannelEntry $expected) {
    $room = channelOfType($type);
    $member = memberOf($room);
    if ($hasTwoFactor) {
        withTwoFactor($member);
    }

    expect(app(ChannelAccessService::class)->entryFor($member, $room))->toBe($expected);
})->with([
    'private secured needs the PIN' => [ChannelType::PrivateSecured, false, ChannelEntry::PinRequired],
    'private secured opens with 2FA' => [ChannelType::PrivateSecured, true, ChannelEntry::Granted],
    'private free entry opens' => [ChannelType::PrivateFreeEntry, false, ChannelEntry::Granted],
    'public secured needs the PIN' => [ChannelType::PublicSecured, false, ChannelEntry::PinRequired],
    'public secured opens with 2FA' => [ChannelType::PublicSecured, true, ChannelEntry::Granted],
    'public free entry opens' => [ChannelType::PublicFreeEntry, false, ChannelEntry::Granted],
]);

test('a signed-in stranger may enter public channels but not private ones', function (ChannelType $type, ChannelEntry $expected) {
    $room = channelOfType($type);

    expect(app(ChannelAccessService::class)->entryFor(User::factory()->create(), $room))->toBe($expected);
})->with([
    'private secured' => [ChannelType::PrivateSecured, ChannelEntry::Denied],
    'private free entry' => [ChannelType::PrivateFreeEntry, ChannelEntry::Denied],
    'public secured' => [ChannelType::PublicSecured, ChannelEntry::PinRequired],
    'public free entry' => [ChannelType::PublicFreeEntry, ChannelEntry::Granted],
]);

test('an invitation that has not been accepted does not open a private channel', function () {
    $room = channelOfType(ChannelType::PrivateFreeEntry);
    $invitee = User::factory()->create();
    $room->inviteUser($invitee);

    expect(app(ChannelAccessService::class)->entryFor($invitee, $room))->toBe(ChannelEntry::Denied);
});

test('the creator may open their private channel', function () {
    $creator = User::factory()->create();
    $room = channelOfType(ChannelType::PrivateFreeEntry, ['created_by_user_id' => $creator->id]);

    expect(app(ChannelAccessService::class)->entryFor($creator, $room))->toBe(ChannelEntry::Granted);
});

test('a member who switched off pinless entry for a channel must use its PIN', function () {
    $room = channelOfType(ChannelType::PrivateSecured);
    $member = withTwoFactor(User::factory()->create());
    $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active', 'biometric_entry' => false]);

    expect(app(ChannelAccessService::class)->entryFor($member, $room))->toBe(ChannelEntry::PinRequired);
});

test('admin channels admit only the ranks allowed in them', function (ChannelType $type, Rank $rank, bool $hasPasskey, ChannelEntry $expected) {
    $room = channelOfType($type);
    $admin = User::factory()->rank($rank)->create();
    if ($hasPasskey) {
        enrollPasskey($admin);
    }

    expect(app(ChannelAccessService::class)->entryFor($admin, $room))->toBe($expected);
})->with([
    'pra admits Admin II with a passkey' => [ChannelType::PrivateAdmin, Rank::AdminTwo, true, ChannelEntry::Granted],
    'pra asks Admin II without a passkey to enroll one' => [ChannelType::PrivateAdmin, Rank::AdminTwo, false, ChannelEntry::PasskeyRequired],
    'pra refuses Admin III' => [ChannelType::PrivateAdmin, Rank::AdminThree, true, ChannelEntry::Denied],
    'pra refuses User I' => [ChannelType::PrivateAdmin, Rank::UserOne, true, ChannelEntry::Denied],
    'pra-2 admits Admin III with its PIN' => [ChannelType::PrivateAdminTwo, Rank::AdminThree, false, ChannelEntry::PinRequired],
    'pra-2 admits Admin III with a passkey' => [ChannelType::PrivateAdminTwo, Rank::AdminThree, true, ChannelEntry::Granted],
    'pra-2 refuses User I' => [ChannelType::PrivateAdminTwo, Rank::UserOne, true, ChannelEntry::Denied],
]);

test('two factor authentication never opens the passkey-only admin channel', function () {
    $room = channelOfType(ChannelType::PrivateAdmin);
    $adminTwo = withTwoFactor(User::factory()->adminTwo()->create());

    expect(app(ChannelAccessService::class)->entryFor($adminTwo, $room))->toBe(ChannelEntry::PasskeyRequired);
});

test('admin one may open any channel', function (ChannelType $type) {
    expect(app(ChannelAccessService::class)->entryFor(User::factory()->adminOne()->create(), channelOfType($type)))
        ->toBe(ChannelEntry::Granted);
})->with(ChannelType::cases());

test('nobody may open a closed channel', function (array $attributes) {
    $room = channelOfType(ChannelType::PublicFreeEntry, $attributes);

    expect(app(ChannelAccessService::class)->entryFor(memberOf($room), $room))->toBe(ChannelEntry::Denied);
})->with([
    'archived' => [['status' => 'archived']],
    'expired' => [['expires_at' => now()->subMinute()]],
]);

/* -------------------------------------------------------------
 * Visibility
 * ------------------------------------------------------------- */

test('a channel is listed only for viewers allowed to know it exists', function (ChannelType $type, Closure $makeViewer, bool $visible) {
    $room = channelOfType($type);

    expect(app(ChannelAccessService::class)->isVisibleTo($makeViewer($room), $room))->toBe($visible);
})->with([
    'public channel to a signed-out visitor' => [ChannelType::PublicSecured, fn (Room $room) => null, true],
    'private channel to a signed-out visitor' => [ChannelType::PrivateSecured, fn (Room $room) => null, false],
    'private channel to a stranger' => [ChannelType::PrivateSecured, fn (Room $room) => User::factory()->create(), false],
    'private channel to a member' => [ChannelType::PrivateSecured, fn (Room $room) => memberOf($room), true],
    'private channel to a pending invitee' => [ChannelType::PrivateSecured, function (Room $room) {
        $invitee = User::factory()->create();
        $room->inviteUser($invitee);

        return $invitee;
    }, true],
    'pra to Admin III' => [ChannelType::PrivateAdmin, fn (Room $room) => User::factory()->rank(Rank::AdminThree)->create(), false],
    'pra-2 to Admin III' => [ChannelType::PrivateAdminTwo, fn (Room $room) => User::factory()->rank(Rank::AdminThree)->create(), true],
]);

test('the system admin channels are seeded and cannot be destroyed', function () {
    $command = Room::where('code', 'PRA')->sole();
    $council = Room::where('code', 'PRA-2')->sole();

    expect($command->type)->toBe(ChannelType::PrivateAdmin);
    expect($council->type)->toBe(ChannelType::PrivateAdminTwo);
    expect($command->is_system && $council->is_system)->toBeTrue();
    expect(User::factory()->adminOne()->create()->can('destroy', $command))->toBeFalse();
});
