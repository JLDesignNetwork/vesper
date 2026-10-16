<?php

use App\Enums\ChannelType;
use App\Mail\ChannelInvitationNotification;
use App\Models\ChannelInvitation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/* -------------------------------------------------------------
 * Creating channels
 * ------------------------------------------------------------- */

test('a member creates a secured channel and lands inside it as its owner', function () {
    $member = User::factory()->create();

    $response = $this->actingAs($member)->post(route('channels.store'), [
        'title' => 'Night Shift',
        'description' => 'Ops chatter',
        'type' => 'prs',
        'pin' => '2468',
    ]);

    $room = Room::where('title', 'Night Shift')->sole();
    $response->assertRedirect(route('rooms.show', $room->code));
    expect($room->type)->toBe(ChannelType::PrivateSecured);
    expect($room->description)->toBe('Ops chatter');
    expect($room->expires_at)->toBeNull();
    expect($room->isCreatedBy($member))->toBeTrue();
    expect($room->verifyPasscode('2468'))->toBeTrue();
    expect(DB::table('room_user')->where('room_id', $room->id)->where('user_id', $member->id)->value('role'))->toBe('owner');
    $this->get(route('rooms.show', $room->code))->assertOk()->assertViewIs('room');
});

test('a channel PIN is never stored or readable in plaintext', function () {
    $this->actingAs(User::factory()->create())->post(route('channels.store'), [
        'title' => 'Vault', 'type' => 'pus', 'pin' => '908172',
    ]);

    $row = (array) DB::table('rooms')->where('title', 'Vault')->first();
    $room = Room::where('title', 'Vault')->sole();

    expect(implode('|', array_map('strval', $row)))->not->toContain('908172');
    expect($room->pin)->toBeNull();
    expect($room->verifyPasscode('908172'))->toBeTrue();
});

test('a free-entry channel needs no PIN', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('channels.store'), ['title' => 'Lobby', 'type' => 'puf'])
        ->assertSessionHasNoErrors();

    expect(Room::where('title', 'Lobby')->value('passcode_hash'))->toBeNull();
});

test('creating a channel is refused when the request is invalid', function (array $payload, string $field, string $message) {
    $this->actingAs(User::factory()->create())
        ->post(route('channels.store'), $payload)
        ->assertSessionHasErrors([$field => $message]);

    expect(Room::where('is_system', false)->count())->toBe(0);
})->with([
    'secured channel without a PIN' => [['title' => 'Safe', 'type' => 'prs'], 'pin', 'The pin field is required.'],
    'PIN shorter than four characters' => [['title' => 'Safe', 'type' => 'prs', 'pin' => '12'], 'pin', 'The pin field must be at least 4 characters.'],
    'missing title' => [['type' => 'puf'], 'title', 'The title field is required.'],
    'member choosing an admin channel type' => [['title' => 'Mine', 'type' => 'pra'], 'type', 'The selected type is invalid.'],
    'unknown channel type' => [['title' => 'Mine', 'type' => 'zzz'], 'type', 'The selected type is invalid.'],
]);

test('admin one may create an additional admin channel', function () {
    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('channels.store'), ['title' => 'War Room', 'type' => 'pra'])
        ->assertSessionHasNoErrors();

    expect(Room::where('title', 'War Room')->value('type'))->toBe(ChannelType::PrivateAdmin);
});

/* -------------------------------------------------------------
 * Creator settings
 * ------------------------------------------------------------- */

test('the creator updates their channel and a blank PIN keeps the current one', function () {
    $creator = User::factory()->create();
    $room = Room::create(['code' => 'SET-1', 'title' => 'Old', 'type' => ChannelType::PrivateSecured, 'pin' => '1111', 'status' => 'active', 'created_by_user_id' => $creator->id]);

    $this->actingAs($creator)->put(route('channels.update', $room->id), [
        'title' => 'New Name',
        'description' => 'Fresh',
        'type' => 'pus',
        'pin' => '',
    ])->assertRedirect(route('profile.show'))->assertSessionHas('status', 'Settings saved for New Name.');

    $room->refresh();
    expect($room->title)->toBe('New Name');
    expect($room->type)->toBe(ChannelType::PublicSecured);
    expect($room->verifyPasscode('1111'))->toBeTrue();
});

test('the creator can replace the channel PIN', function () {
    $creator = User::factory()->create();
    $room = Room::create(['code' => 'SET-2', 'title' => 'Ops', 'type' => ChannelType::PrivateSecured, 'pin' => '1111', 'status' => 'active', 'created_by_user_id' => $creator->id]);

    $this->actingAs($creator)->put(route('channels.update', $room->id), ['title' => 'Ops', 'type' => 'prs', 'pin' => '7777']);

    expect($room->fresh()->verifyPasscode('7777'))->toBeTrue();
    expect($room->fresh()->verifyPasscode('1111'))->toBeFalse();
});

test('only the creator or admin one can change channel settings', function () {
    $room = Room::create(['code' => 'SET-3', 'title' => 'Theirs', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active', 'created_by_user_id' => User::factory()->create()->id]);

    $this->actingAs(memberOf($room))
        ->put(route('channels.update', $room->id), ['title' => 'Hijacked', 'type' => 'puf'])
        ->assertForbidden();

    expect($room->fresh()->title)->toBe('Theirs');
});

test('a system channel keeps its type when its settings are saved', function () {
    $command = Room::where('code', 'PRA')->sole();

    $this->actingAs(User::factory()->adminOne()->create())
        ->put(route('channels.update', $command->id), ['title' => 'Command', 'type' => 'puf']);

    expect($command->fresh()->type)->toBe(ChannelType::PrivateAdmin);
    expect($command->fresh()->title)->toBe('Command');
});

/* -------------------------------------------------------------
 * Joining
 * ------------------------------------------------------------- */

test('opening a public free-entry channel from the directory joins it', function () {
    $room = Room::create(['code' => 'PUB-1', 'title' => 'Plaza', 'type' => ChannelType::PublicFreeEntry, 'status' => 'active']);
    $visitor = User::factory()->create();

    $this->actingAs($visitor)->get(route('rooms.show', 'PUB-1'))->assertOk()->assertViewIs('room');

    expect($room->isMember($visitor))->toBeTrue();
});

test('a public secured channel is joined after entering its PIN', function () {
    $room = Room::create(['code' => 'PUB-2', 'title' => 'Guarded Plaza', 'type' => ChannelType::PublicSecured, 'pin' => '5555', 'status' => 'active']);
    $visitor = User::factory()->create();

    $this->actingAs($visitor)->get(route('rooms.show', 'PUB-2'))->assertViewIs('channel_entry');
    expect($room->isMember($visitor))->toBeFalse();

    $this->post(route('rooms.verify', 'PUB-2'), ['passcode' => '5555'])->assertRedirect(route('rooms.show', 'PUB-2'));

    expect($room->isMember($visitor))->toBeTrue();
});

test('a private channel cannot be joined with its code and PIN alone', function () {
    $room = Room::create(['code' => 'PRIV-1', 'title' => 'Back Office', 'type' => ChannelType::PrivateSecured, 'pin' => '5555', 'status' => 'active']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('rooms.verify', 'PRIV-1'), ['passcode' => '5555'])
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors(['code' => 'Channel does not exist, has expired, or was purged.']);

    $this->get(route('rooms.show', 'PRIV-1'))->assertRedirect(route('profile.show'));
    expect($room->isMember($stranger))->toBeFalse();
    expect(session()->has("room_clearance_{$room->id}"))->toBeFalse();
});

test('the directory lists only open public channels and filters by search', function () {
    Room::create(['code' => 'DIR-1', 'title' => 'Chess Club', 'type' => ChannelType::PublicFreeEntry, 'status' => 'active']);
    Room::create(['code' => 'DIR-2', 'title' => 'Book Club', 'type' => ChannelType::PublicSecured, 'pin' => '1234', 'status' => 'active']);
    Room::create(['code' => 'DIR-3', 'title' => 'Closed Club', 'type' => ChannelType::PublicFreeEntry, 'status' => 'archived']);
    Room::create(['code' => 'DIR-4', 'title' => 'Expired Club', 'type' => ChannelType::PublicFreeEntry, 'status' => 'active', 'expires_at' => now()->subHour()]);
    Room::create(['code' => 'DIR-5', 'title' => 'Secret Club', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active']);

    $this->actingAs(User::factory()->create())->get(route('channels.directory'))
        ->assertOk()
        ->assertSee('Chess Club')
        ->assertSee('Book Club')
        ->assertSee(route('rooms.show', 'DIR-1'))
        ->assertDontSee('Closed Club')
        ->assertDontSee('Expired Club')
        ->assertDontSee('Secret Club')
        ->assertDontSee('Admin Command');

    $this->get(route('channels.directory', ['q' => 'chess']))
        ->assertSee('Chess Club')
        ->assertDontSee('Book Club');
});

test('the directory escapes channel names and descriptions', function () {
    Room::create(['code' => 'XSS-1', 'title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>', 'type' => ChannelType::PublicFreeEntry, 'status' => 'active']);

    $this->get(route('channels.directory'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(2)>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

/* -------------------------------------------------------------
 * Invitations and system channels
 * ------------------------------------------------------------- */

test('an invitation email links to the channel without revealing its PIN', function () {
    Mail::fake();
    $room = Room::create(['code' => 'INV-PIN', 'title' => 'Ops', 'type' => ChannelType::PrivateSecured, 'pin' => '864213', 'status' => 'active']);
    $invitee = User::factory()->create();

    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('admin.channels.invite', $room->id), ['user_id' => $invitee->id])
        ->assertSessionHas('status', "Direct invitation issued to Member [{$invitee->name}] for channel [INV-PIN].");

    Mail::assertQueued(ChannelInvitationNotification::class, fn (ChannelInvitationNotification $mail) => ! str_contains($mail->render(), '864213')
        && str_contains($mail->render(), route('rooms.show', 'INV-PIN')));
});

test('admin one can generate a shareable invitation link and code', function () {
    $room = Room::create(['code' => 'INV-LINK', 'title' => 'Ops', 'type' => ChannelType::PrivateSecured, 'pin' => '1234', 'status' => 'active']);

    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('admin.channels.invite', $room->id), ['max_uses' => 5])
        ->assertRedirect(route('admin.channels.index'))
        ->assertSessionHas('generated_invite');

    $invitation = ChannelInvitation::where('room_id', $room->id)->sole();
    expect($invitation->max_uses)->toBe(5);
});

test('system channels cannot be archived or deleted from the admin console', function (string $method, string $routeName, string $error) {
    $command = Room::where('code', 'PRA')->sole();

    $this->actingAs(User::factory()->adminOne()->create())
        ->call($method, route($routeName, $command->id))
        ->assertSessionHasErrors(['channel' => $error]);

    expect($command->fresh()->status)->toBe('active');
})->with([
    'archive' => ['POST', 'admin.channels.toggle', 'System channels cannot be archived.'],
    'delete' => ['DELETE', 'admin.channels.destroy', 'System channels cannot be deleted.'],
]);

test('admin one editing a channel with a blank PIN keeps the current PIN', function () {
    $room = Room::create(['code' => 'ADM-EDIT', 'title' => 'Ops', 'type' => ChannelType::PrivateSecured, 'pin' => '1111', 'status' => 'active']);

    $this->actingAs(User::factory()->adminOne()->create())
        ->put(route('admin.channels.update', $room->id), ['title' => 'Ops 2', 'pin' => '', 'allowed_languages' => ['en']])
        ->assertRedirect(route('admin.channels.index'));

    expect($room->fresh()->title)->toBe('Ops 2');
    expect($room->fresh()->verifyPasscode('1111'))->toBeTrue();
});

/* -------------------------------------------------------------
 * Profile wiring
 * ------------------------------------------------------------- */

test('the profile offers channel creation, the directory, and settings only on owned channels', function () {
    $member = User::factory()->create();
    $owned = Room::create(['code' => 'OWN-1', 'title' => 'Mine "quoted"', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active', 'created_by_user_id' => $member->id]);
    $owned->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'owner', 'status' => 'active']);
    $joined = Room::create(['code' => 'JOIN-1', 'title' => 'Theirs', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active', 'created_by_user_id' => User::factory()->create()->id]);
    $joined->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active']);

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertSee(route('channels.store'))
        ->assertSee(route('channels.directory'))
        ->assertSee(route('channels.update', $owned->id))
        ->assertDontSee(route('channels.update', $joined->id))
        ->assertSee('data-title="Mine &quot;quoted&quot;"', false)
        ->assertDontSee('<option value="pra"', false);
});

test('admin one may pick admin channel types when creating a channel', function () {
    $this->actingAs(User::factory()->adminOne()->create())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('<option value="pra"', false);
});
