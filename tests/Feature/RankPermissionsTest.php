<?php

use App\Enums\AuditAction;
use App\Enums\Rank;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

$everyRankExceptAdminOne = [
    'Admin II' => [Rank::AdminTwo],
    'Admin III' => [Rank::AdminThree],
    'User I' => [Rank::UserOne],
    'User II' => [Rank::UserTwo],
];

/* -------------------------------------------------------------
 * Policy and gate matrix
 * ------------------------------------------------------------- */

test('only admin one may see fields another member has hidden', function (Rank $rank, bool $allowed) {
    $viewer = User::factory()->rank($rank)->create();
    $subject = User::factory()->create(['hide_age' => true]);

    expect(Gate::forUser($viewer)->allows('viewHiddenFields', $subject))->toBe($allowed);
})->with([
    'Admin I' => [Rank::AdminOne, true],
    'Admin II' => [Rank::AdminTwo, false],
    'Admin III' => [Rank::AdminThree, false],
    'User I' => [Rank::UserOne, false],
    'User II' => [Rank::UserTwo, false],
]);

test('every member may see their own hidden fields', function (Rank $rank) {
    $member = User::factory()->rank($rank)->create(['hide_age' => true]);

    expect(Gate::forUser($member)->allows('viewHiddenFields', $member))->toBeTrue();
})->with($everyRankExceptAdminOne);

test('only admin one passes the platform-wide admin gates', function (string $ability, Rank $rank, bool $allowed) {
    $user = User::factory()->rank($rank)->create();

    expect(Gate::forUser($user)->allows($ability))->toBe($allowed);
})->with(['accessAdminPanel', 'viewGeolocation'])->with([
    'Admin I' => [Rank::AdminOne, true],
    'Admin II' => [Rank::AdminTwo, false],
    'Admin III' => [Rank::AdminThree, false],
    'User I' => [Rank::UserOne, false],
    'User II' => [Rank::UserTwo, false],
]);

test('only admin one may override channel access', function (Rank $rank, bool $allowed) {
    $user = User::factory()->rank($rank)->create();
    $room = Room::create(['code' => 'OVR-1', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);

    expect(Gate::forUser($user)->allows('overrideAccess', $room))->toBe($allowed);
})->with([
    'Admin I' => [Rank::AdminOne, true],
    'Admin II' => [Rank::AdminTwo, false],
    'Admin III' => [Rank::AdminThree, false],
    'User I' => [Rank::UserOne, false],
    'User II' => [Rank::UserTwo, false],
]);

test('the channel creator and admin one may pin messages and destroy the channel', function (string $ability, Rank $rank, bool $isCreator, bool $allowed) {
    $user = User::factory()->rank($rank)->create();
    $room = Room::create([
        'code' => 'PIN-1',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $isCreator ? $user->id : User::factory()->create()->id,
    ]);

    expect(Gate::forUser($user)->allows($ability, $room))->toBe($allowed);
})->with(['pinMessages', 'destroy'])->with([
    'Admin I in any channel' => [Rank::AdminOne, false, true],
    'Admin II in a channel they did not create' => [Rank::AdminTwo, false, false],
    'Admin III in a channel they did not create' => [Rank::AdminThree, false, false],
    'User II in a channel they did not create' => [Rank::UserTwo, false, false],
    'User II who created the channel' => [Rank::UserTwo, true, true],
]);

test('only admin one may appoint or revoke admin two, and never on themself', function (Rank $rank, bool $onSelf, bool $allowed) {
    $actor = User::factory()->rank($rank)->create();
    $subject = $onSelf ? $actor : User::factory()->create();

    expect(Gate::forUser($actor)->allows('manageAdminTwo', $subject))->toBe($allowed);
})->with([
    'Admin I on another member' => [Rank::AdminOne, false, true],
    'Admin I on themself' => [Rank::AdminOne, true, false],
    'Admin II on another member' => [Rank::AdminTwo, false, false],
    'Admin III on another member' => [Rank::AdminThree, false, false],
]);

/* -------------------------------------------------------------
 * Endpoints apply the checks
 * ------------------------------------------------------------- */

test('admin two cannot open the admin panel', function () {
    $this->actingAs(User::factory()->adminTwo()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('profile.show'));
});

test('admin two does not see fields a member has hidden in the channel profile card', function () {
    $viewer = User::factory()->adminTwo()->create();
    User::factory()->create([
        'name' => 'QuietMember',
        'birthday' => '1990-06-20',
        'hide_age' => true,
        'hide_bio' => true,
        'bio' => 'Private bio',
        'preferred_locale' => 'fr',
    ]);
    $room = Room::create(['code' => 'CARD-1', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);

    $response = $this->actingAs($viewer)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => 'CARD-1', 'name' => 'QuietMember']));

    $response->assertOk()
        ->assertJsonPath('age', null)
        ->assertJsonPath('bio', null)
        ->assertJsonPath('birthday', 'June 20')
        ->assertJsonPath('preferred_locale', null)
        ->assertJsonPath('is_admin', false);
});

test('admin one sees fields a member has hidden in the channel profile card', function () {
    $viewer = User::factory()->adminOne()->create();
    User::factory()->create([
        'name' => 'QuietMember',
        'birthday' => now()->subYears(36)->subDay()->toDateString(),
        'hide_age' => true,
        'hide_bio' => true,
        'bio' => 'Private bio',
    ]);
    $room = Room::create(['code' => 'CARD-2', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);

    $this->actingAs($viewer)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => 'CARD-2', 'name' => 'QuietMember']))
        ->assertOk()
        ->assertJsonPath('age', 36)
        ->assertJsonPath('bio', 'Private bio')
        ->assertJsonPath('is_admin', true);
});

test('admin two who is not a member gets the entry gate instead of the channel', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    $room = Room::create(['code' => 'GATE-2', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);

    $response = $this->actingAs($adminTwo)->get(route('rooms.show', 'GATE-2'));

    $response->assertRedirect(route('profile.show'));
    $response->assertSessionMissing("room_clearance_{$room->id}");
    expect(AdminAuditLog::count())->toBe(0);
});

test('admin one entering a channel they do not belong to is audited', function () {
    $owner = User::factory()->adminOne()->create();
    $room = Room::create(['code' => 'OVR-2', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);

    $response = $this->actingAs($owner)->get(route('rooms.show', 'OVR-2'));

    $response->assertOk()->assertViewIs('room');
    $entry = AdminAuditLog::sole();
    expect($entry->action)->toBe(AuditAction::ChannelAccessOverride);
    expect($entry->actor_user_id)->toBe($owner->id);
    expect($entry->room_id)->toBe($room->id);
});

test('admin one entering a channel they belong to is not audited as an override', function () {
    $owner = User::factory()->adminOne()->create();
    $room = Room::create(['code' => 'OVR-3', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);
    $room->belongsToMany(User::class, 'room_user')->attach($owner->id, ['role' => 'member', 'status' => 'active']);

    $this->actingAs($owner)->get(route('rooms.show', 'OVR-3'))->assertOk();

    expect(AdminAuditLog::count())->toBe(0);
});

test('the channel view offers the radar map, pinning and purging only to the ranks allowed them', function (Rank $rank, bool $isCreator, bool $canManage, bool $canViewRadar) {
    $user = User::factory()->rank($rank)->create();
    $room = Room::create([
        'code' => 'VIEW-1',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $isCreator ? $user->id : null,
    ]);
    $room->belongsToMany(User::class, 'room_user')->attach($user->id, ['role' => 'member', 'status' => 'active']);

    $this->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('rooms.show', 'VIEW-1'))
        ->assertOk()
        ->assertViewHas('canPinMessages', $canManage)
        ->assertViewHas('canDestroyChannel', $canManage)
        ->assertViewHas('canViewRadar', $canViewRadar);
})->with([
    'Admin I' => [Rank::AdminOne, false, true, true],
    'Admin II' => [Rank::AdminTwo, false, false, false],
    'User II who created the channel' => [Rank::UserTwo, true, true, false],
]);

test('admin two cannot pin in a channel they did not create', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    $room = Room::create(['code' => 'PIN-2', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);
    $message = Message::create(['room_id' => $room->id, 'sender_session_id' => 's', 'sender_name' => 'X', 'content' => 'Hi']);

    $this->actingAs($adminTwo)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson(route('rooms.pin', ['room' => 'PIN-2', 'message' => $message->id]))
        ->assertForbidden();

    expect($room->fresh()->pinnedMessageIds())->toBe([]);
});

test('messages show only the public rank label of their sender', function (Rank $senderRank, string $expectedRole) {
    $sender = User::factory()->rank($senderRank)->create(['name' => 'Sender']);
    $room = Room::create(['code' => 'LBL-1', 'passcode_hash' => Hash::make('secret'), 'status' => 'active']);
    Message::create([
        'room_id' => $room->id,
        'user_id' => $sender->id,
        'sender_session_id' => 'sender-session',
        'sender_name' => 'Sender',
        'content' => 'Hello',
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('messages.index', 'LBL-1'))
        ->assertOk()
        ->assertJsonPath('messages.0.sender_role', $expectedRole);
})->with([
    'Admin I shows as admin' => [Rank::AdminOne, 'admin'],
    'Admin II shows as admin' => [Rank::AdminTwo, 'admin'],
    'Admin III shows as member' => [Rank::AdminThree, 'member'],
    'User I shows as member' => [Rank::UserOne, 'member'],
]);

/* -------------------------------------------------------------
 * Audit log visibility
 * ------------------------------------------------------------- */

test('audit entries are visible according to the reader\'s rank', function (Rank $rank, int $expectedVisible) {
    AdminAuditLog::factory()->create();
    AdminAuditLog::factory()->moderation()->create();

    $reader = User::factory()->rank($rank)->create();

    expect(AdminAuditLog::visibleTo($reader)->count())->toBe($expectedVisible);
})->with([
    'Admin I reads every entry' => [Rank::AdminOne, 2],
    'Admin II reads moderation entries only' => [Rank::AdminTwo, 1],
    'Admin III reads none' => [Rank::AdminThree, 0],
    'User II reads none' => [Rank::UserTwo, 0],
]);
