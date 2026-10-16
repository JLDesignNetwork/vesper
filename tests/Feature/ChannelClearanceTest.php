<?php

use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Models\Room;
use App\Models\User;
use App\Services\RankService;
use Illuminate\Support\Str;

/**
 * Open a channel through its PIN gate as the given member.
 */
function openWithPin(Room $room, User $member, string $pin = '4321'): void
{
    test()->actingAs($member)
        ->post(route('rooms.verify', $room->code), ['passcode' => $pin])
        ->assertRedirect(route('rooms.show', $room->code));
}

function securedChannel(ChannelType $type = ChannelType::PrivateSecured): Room
{
    return Room::create(['code' => 'CLR-'.strtoupper(Str::random(4)), 'type' => $type, 'pin' => '4321', 'status' => 'active']);
}

test('a secured channel closes after fifteen minutes without member activity', function () {
    $room = securedChannel();
    $member = memberOf($room);
    openWithPin($room, $member);

    $this->travel(16)->minutes();

    $this->getJson(route('messages.index', $room->code))->assertForbidden();
    expect(session()->has("room_clearance_{$room->id}"))->toBeFalse();
});

test('polling for messages does not keep a secured channel open', function () {
    $room = securedChannel();
    $member = memberOf($room);
    openWithPin($room, $member);

    $this->travel(10)->minutes();
    $this->getJson(route('messages.index', $room->code))->assertOk();
    $this->travel(6)->minutes();

    $this->getJson(route('messages.index', $room->code))->assertForbidden();
});

test('member activity keeps a secured channel open', function (string $action) {
    $room = securedChannel();
    $member = memberOf($room);
    openWithPin($room, $member);

    $this->travel(10)->minutes();
    match ($action) {
        'heartbeat' => $this->postJson(route('rooms.heartbeat', $room->code))->assertOk(),
        'message' => $this->postJson(route('messages.store', $room->code), ['content' => 'Still here'])->assertOk(),
    };
    $this->travel(10)->minutes();

    $this->getJson(route('messages.index', $room->code))->assertOk();
})->with(['heartbeat', 'message']);

test('a free-entry channel does not close while idle', function () {
    $room = Room::create(['code' => 'FREE-1', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active']);
    $member = memberOf($room);
    $this->actingAs($member)->get(route('rooms.show', $room->code))->assertOk()->assertViewIs('room');

    $this->travel(2)->hours();

    $this->getJson(route('messages.index', $room->code))->assertOk();
});

test('locking a channel closes it immediately', function () {
    $room = securedChannel();
    $member = memberOf($room);
    openWithPin($room, $member);

    $this->post(route('rooms.lock', $room->code));

    $this->getJson(route('messages.index', $room->code))->assertForbidden();
});

test('the channel page tells the browser when to close a secured channel', function (ChannelType $type, ?int $expectedSeconds) {
    $room = Room::create(['code' => 'VIEW-T', 'type' => $type, 'pin' => '4321', 'status' => 'active']);
    $member = memberOf($room);

    $this->actingAs($member)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertViewHas('clearanceIdleSeconds', $expectedSeconds);
})->with([
    'secured channel closes after 15 minutes' => [ChannelType::PrivateSecured, 900],
    'free-entry channel has no idle limit' => [ChannelType::PrivateFreeEntry, null],
]);

test('losing admin three ends access to the all-admins channel on the next request', function () {
    $council = Room::where('code', 'PRA-2')->sole();
    $council->update(['pin' => '9999']);
    $adminThree = User::factory()->rank(Rank::AdminThree)->create();
    openWithPin($council, $adminThree, '9999');
    $this->getJson(route('messages.index', $council->code))->assertOk();

    app(RankService::class)->recompute($adminThree);
    expect($adminThree->fresh()->rank)->toBe(Rank::UserTwo);

    $this->actingAs($adminThree->fresh())
        ->getJson(route('messages.index', $council->code))
        ->assertForbidden()
        ->assertJsonPath('message', 'You no longer have access to this channel.');
});

test('a member who switched off pinless entry is sent to the PIN gate', function () {
    $room = securedChannel();
    $member = User::factory()->create();
    $member->forceFill(['two_factor_secret' => 's', 'two_factor_confirmed_at' => now()])->save();
    $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active', 'biometric_entry' => false]);

    $this->actingAs($member)->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertViewIs('channel_entry');
});

test('a member with two factor authentication opens a secured channel without its PIN', function () {
    $room = securedChannel();
    $member = memberOf($room);
    $member->forceFill(['two_factor_secret' => 's', 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($member)->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertViewIs('room');
});
