<?php

use App\Enums\ChannelType;
use App\Models\AccessLog;
use App\Models\ChannelInvitation;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\Room;
use App\Models\User;

/**
 * Guest mode was retired (owner decision Q7 / A2). A signed-out visitor may browse
 * the public directory and nothing else — even holding a clearance flag left over in
 * their session from before the change.
 */
function guestTargetRoom(): array
{
    $owner = User::factory()->create();
    $room = Room::create([
        'code' => 'GUEST-T',
        'title' => 'Open Square',
        'type' => ChannelType::PublicFreeEntry,
        'status' => 'active',
        'created_by_user_id' => $owner->id,
    ]);
    $message = Message::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'sender_session_id' => 'owner-session',
        'sender_name' => $owner->name,
        'content' => 'Members only',
    ]);
    $invitation = ChannelInvitation::createForRoom(room: $room, creator: $owner);

    return [$room, $message, $invitation];
}

test('a signed-out visitor is refused by every channel endpoint and nothing is written', function (string $method, Closure $url, bool $expectsJson) {
    [$room, $message, $invitation] = guestTargetRoom();
    $messagesBefore = Message::count();

    $request = $this->withSession(["room_clearance_{$room->id}" => true, "room_alias_{$room->id}" => 'Lurker']);
    $target = $url($room, $message, $invitation);
    $payload = ['content' => 'Hello', 'passcode' => '1234', 'code' => $invitation->code, 'title' => 'Mine', 'type' => 'puf', 'emoji' => '👍', 'text' => 'Hi', 'target_lang' => 'fr', 'latitude' => 1, 'longitude' => 1];

    $response = $expectsJson ? $this->json($method, $target, $payload) : $request->call($method, $target, $payload);

    $expectsJson ? $response->assertUnauthorized() : $response->assertRedirect(route('login'));
    expect(Message::count())->toBe($messagesBefore);
    expect(AccessLog::count())->toBe(0);
    expect(Reaction::count())->toBe(0);
    expect($room->members()->count())->toBe(0);
    expect(Room::where('title', 'Mine')->exists())->toBeFalse();
})->with([
    'open the channel' => ['GET', fn (Room $room) => route('rooms.show', $room->code), false],
    'enter a PIN' => ['POST', fn (Room $room) => route('rooms.verify', $room->code), false],
    'read messages' => ['GET', fn (Room $room) => route('messages.index', $room->code), true],
    'send a message' => ['POST', fn (Room $room) => route('messages.store', $room->code), true],
    'react to a message' => ['POST', fn (Room $room, Message $message) => route('messages.react', [$room->code, $message->id]), true],
    'pin a message' => ['POST', fn (Room $room, Message $message) => route('rooms.pin', [$room->code, $message->id]), true],
    'download an attachment' => ['GET', fn (Room $room, Message $message) => route('messages.attachment', [$room->code, $message->id]), false],
    'translate a message' => ['POST', fn (Room $room) => route('messages.translate', $room->code), true],
    'translate a batch' => ['POST', fn (Room $room) => route('messages.translate-batch', $room->code), true],
    'view a member card' => ['GET', fn (Room $room) => route('rooms.member-profile', [$room->code, 'name' => 'x']), true],
    'view the radar' => ['GET', fn (Room $room) => route('intel.radar', $room->code), true],
    'sync GPS' => ['POST', fn (Room $room) => route('intel.gps', $room->code), true],
    'keep the channel open' => ['POST', fn (Room $room) => route('rooms.heartbeat', $room->code), true],
    'purge the channel' => ['POST', fn (Room $room) => route('rooms.nuke', $room->code), false],
    'accept an invite link' => ['POST', fn (Room $room, Message $message, ChannelInvitation $invitation) => route('invites.accept', $invitation->token), false],
    'redeem an invite code' => ['POST', fn () => route('channels.redeem'), false],
    'enter from the channel hub' => ['POST', fn (Room $room) => route('channels.enter', $room->id), false],
    'create a channel' => ['POST', fn () => route('channels.store'), false],
    'change channel settings' => ['PUT', fn (Room $room) => route('channels.update', $room->id), false],
    'open the admin console' => ['GET', fn () => route('admin.dashboard'), false],
]);

test('a channel purge attempted by a signed-out visitor leaves the channel intact', function () {
    [$room] = guestTargetRoom();

    $this->withSession(["room_clearance_{$room->id}" => true])
        ->post(route('rooms.nuke', $room->code))
        ->assertRedirect(route('login'));

    expect(Room::find($room->id))->not->toBeNull();
});

test('a signed-out visitor following a channel link signs in and returns to the channel', function () {
    [$room] = guestTargetRoom();
    $member = User::factory()->create(['email' => 'member@vesper.test']);

    $this->get(route('rooms.show', $room->code))->assertRedirect(route('login'));
    $this->post(route('login.post'), ['login' => 'member@vesper.test', 'password' => 'password'])
        ->assertRedirect(route('rooms.show', $room->code));

    $this->assertAuthenticatedAs($member);
});

test('a signed-out visitor can browse the public directory with limited details', function () {
    [$room] = guestTargetRoom();
    $room->update(['description' => 'Town square for everyone']);
    Room::create(['code' => 'HIDDEN-1', 'title' => 'Back Room', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active']);

    $this->get(route('channels.directory'))
        ->assertOk()
        ->assertSee('Open Square')
        ->assertSee('Town square for everyone')
        ->assertSee('Sign in to join')
        ->assertDontSee('Back Room')
        ->assertDontSee('Members only');
});
