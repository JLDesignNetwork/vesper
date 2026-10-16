<?php

use App\Enums\AuditAction;
use App\Enums\AuditVisibility;
use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Enums\ReportOrigin;
use App\Models\AdminAuditLog;
use App\Models\ChannelInvitation;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Services\ChannelAccessService;
use App\Services\RankService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function kickChannel(User $creator, ChannelType $type = ChannelType::PrivateFreeEntry): Room
{
    $room = Room::create([
        'code' => 'KCK-'.strtoupper(Str::random(4)),
        'type' => $type,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ]);
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    return $room;
}

function kickAs(User $user, Room $room, User $target)
{
    return test()->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson(route('rooms.kick', ['room' => $room->code, 'tag' => $target->tag]));
}

function membershipOf(Room $room, User $user): ?object
{
    return DB::table('room_user')->where('room_id', $room->id)->where('user_id', $user->id)->first();
}

/**
 * Kick the member through the endpoint and return them.
 */
function kickedFrom(Room $room): User
{
    $member = memberOf($room);
    kickAs($room->creator, $room, $member)->assertOk();

    return $member;
}

/* -------------------------------------------------------------
 * Kicking
 * ------------------------------------------------------------- */

test('the creator kicks a member, and the kick is audited for moderators', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = memberOf($room, ['tag' => 'kick_me']);

    kickAs($creator, $room, $member)
        ->assertOk()
        ->assertJsonPath('message', '@kick_me was removed from this channel and cannot rejoin.');

    $membership = membershipOf($room, $member);
    expect($membership->status)->toBe('kicked');
    expect((int) $membership->kicked_by_user_id)->toBe($creator->id);
    expect($membership->kicked_at)->not->toBeNull();

    $log = AdminAuditLog::where('action', AuditAction::MemberKicked)->sole();
    expect($log->visibility)->toBe(AuditVisibility::Moderation);
    expect($log->subject_user_id)->toBe($member->id);
});

test('only the channel creator can kick', function (Closure $actor) {
    $room = kickChannel(User::factory()->create());
    $member = memberOf($room);

    kickAs($actor($room), $room, $member)->assertForbidden();

    expect(membershipOf($room, $member)->status)->toBe('active');
})->with([
    'another member' => [fn (Room $room) => memberOf($room)],
    'Admin I' => [fn (Room $room) => User::factory()->adminOne()->create()],
]);

test('kicking Admin I or II files a report and answers exactly like a kick', function (Rank $rank) {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $admin = memberOf($room, ['tag' => 'kick_me']);
    $admin->forceFill(['rank' => $rank])->save();

    kickAs($creator, $room, $admin)
        ->assertOk()
        ->assertJsonPath('message', '@kick_me was removed from this channel and cannot rejoin.');

    expect(membershipOf($room, $admin)->status)->toBe('active');
    $report = Report::where('subject_user_id', $admin->id)->sole();
    expect($report->origin)->toBe(ReportOrigin::KickAttempt);
    expect($report->room_id)->toBe($room->id);
})->with([Rank::AdminOne, Rank::AdminTwo]);

test('an Admin III is kicked like any other member', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $adminThree = memberOf($room);
    $adminThree->forceFill(['rank' => Rank::AdminThree])->save();

    kickAs($creator, $room, $adminThree)->assertOk();

    expect(membershipOf($room, $adminThree)->status)->toBe('kicked');
    expect(Report::count())->toBe(0);
});

test('the creator cannot kick someone who is not a member, or themself', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);

    kickAs($creator, $room, User::factory()->create())->assertNotFound();
    kickAs($creator, $room, $creator)->assertUnprocessable();

    expect(membershipOf($room, $creator)->status)->toBe('active');
});

test('a kick lowers the creator\'s rank when a channel stops qualifying', function () {
    $creator = User::factory()->create();
    $rooms = collect(range(1, 3))->map(fn () => channelOwnedBy($creator));
    expect(app(RankService::class)->recompute($creator))->toBe(Rank::UserOne);

    $member = $rooms->first()->members()->where('users.id', '!=', $creator->id)->first();
    kickAs($creator, $rooms->first(), $member)->assertOk();

    expect($creator->fresh()->rank)->toBe(Rank::UserTwo);
});

/* -------------------------------------------------------------
 * A kicked member cannot come back
 * ------------------------------------------------------------- */

test('a kicked member loses access on their very next request', function () {
    $room = kickChannel(User::factory()->create());
    $member = memberOf($room);
    $session = ["room_clearance_{$room->id}" => true];
    $this->actingAs($member)->withSession($session)->getJson(route('messages.index', $room->code))->assertOk();

    kickAs($room->creator, $room, $member)->assertOk();

    $this->actingAs($member)->withSession($session)
        ->getJson(route('messages.index', $room->code))
        ->assertForbidden()
        ->assertJsonPath('message', 'You no longer have access to this channel.');
});

test('a kicked member cannot reopen the channel', function (ChannelType $type) {
    $room = kickChannel(User::factory()->create(), $type);
    $member = kickedFrom($room);

    $this->actingAs($member)->get(route('rooms.show', $room->code))->assertRedirect(route('profile.show'));
    $this->actingAs($member)->post(route('channels.enter', $room->id))->assertRedirect(route('profile.show'));

    expect(membershipOf($room, $member)->status)->toBe('kicked');
    expect(app(ChannelAccessService::class)->entryFor($member, $room)->value)->toBe('denied');
})->with([ChannelType::PrivateFreeEntry, ChannelType::PublicFreeEntry]);

test('a kicked member no longer sees a private channel', function () {
    $room = kickChannel(User::factory()->create());
    $member = kickedFrom($room);

    expect(app(ChannelAccessService::class)->isVisibleTo($member, $room))->toBeFalse();
});

test('a kicked member cannot rejoin with an invite link or code', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = kickedFrom($room);
    $invitation = ChannelInvitation::createForRoom($room, $creator, maxUses: 5);

    $this->actingAs($member)
        ->post(route('invites.accept', $invitation->token))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHasErrors(['channel' => 'This invitation cannot be used with your account.']);
    $this->actingAs($member)
        ->from(route('profile.show'))
        ->post(route('channels.redeem'), ['code' => $invitation->code])
        ->assertSessionHasErrors(['code' => 'This invitation cannot be used with your account.']);

    expect(membershipOf($room, $member)->status)->toBe('kicked');
    expect($invitation->fresh()->uses_count)->toBe(0);
});

test('Admin I cannot directly invite a kicked member back', function () {
    $room = kickChannel(User::factory()->create());
    $member = kickedFrom($room);

    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('admin.channels.invite', $room->id), ['user_id' => $member->id, 'send_email' => false])
        ->assertSessionHasErrors(['invite' => 'This member was kicked from the channel and cannot be invited back.']);

    expect(membershipOf($room, $member)->status)->toBe('kicked');
});

/* -------------------------------------------------------------
 * Member card
 * ------------------------------------------------------------- */

test('the member card offers kicking only to the channel creator', function (Closure $viewer, bool $canKick) {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = memberOf($room, ['tag' => 'card_member']);

    $this->actingAs($viewer($room, $creator))
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'tag' => 'card_member']))
        ->assertOk()
        ->assertJsonPath('can_kick', $canKick)
        ->assertJsonPath('shows_as_admin', false);
})->with([
    'creator' => [fn (Room $room, User $creator) => $creator, true],
    'another member' => [fn (Room $room, User $creator) => memberOf($room), false],
]);

/* -------------------------------------------------------------
 * Allow back
 * ------------------------------------------------------------- */

function allowBackAs(User $user, Room $room, User $target)
{
    return test()->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->deleteJson(route('rooms.kick.lift', ['room' => $room->code, 'tag' => $target->tag]));
}

test('the creator allows a kicked member back, and it is audited', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = kickedFrom($room);

    allowBackAs($creator, $room, $member)
        ->assertOk()
        ->assertJsonPath('message', "@{$member->tag} is allowed back and can rejoin with an invitation.");

    $membership = membershipOf($room, $member);
    expect($membership->status)->toBe('declined');
    expect($membership->kicked_by_user_id)->toBeNull();
    expect($membership->kicked_at)->toBeNull();
    expect(AdminAuditLog::where('action', AuditAction::KickLifted)->sole()->visibility)->toBe(AuditVisibility::Moderation);
});

test('a member who is allowed back can rejoin with an invitation code', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = kickedFrom($room);
    allowBackAs($creator, $room, $member)->assertOk();
    $invitation = ChannelInvitation::createForRoom($room, $creator, maxUses: 5);

    $this->actingAs($member)->post(route('channels.redeem'), ['code' => $invitation->code])->assertSessionHasNoErrors();

    expect(membershipOf($room, $member)->status)->toBe('active');
});

test('a member who is allowed back can reopen a public channel', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator, ChannelType::PublicFreeEntry);
    $member = kickedFrom($room);

    allowBackAs($creator, $room, $member)
        ->assertJsonPath('message', "@{$member->tag} is allowed back and can rejoin by opening the channel.");

    $this->flushSession();
    $this->actingAs($member)->get(route('rooms.show', $room->code))->assertOk();
    expect(membershipOf($room, $member)->status)->toBe('active');
});

test('a member allowed back cannot use an old clearance to read a private channel', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = memberOf($room);
    $session = ["room_clearance_{$room->id}" => true];
    kickAs($creator, $room, $member)->assertOk();
    allowBackAs($creator, $room, $member)->assertOk();

    $this->actingAs($member)->withSession($session)
        ->getJson(route('messages.index', $room->code))
        ->assertForbidden();
});

test('only the channel creator can allow a member back', function (Closure $actor) {
    $room = kickChannel(User::factory()->create());
    $member = kickedFrom($room);

    allowBackAs($actor($room), $room, $member)->assertForbidden();

    expect(membershipOf($room, $member)->status)->toBe('kicked');
})->with([
    'another member' => [fn (Room $room) => memberOf($room)],
    'Admin I' => [fn (Room $room) => User::factory()->adminOne()->create()],
]);

test('allowing back someone who was not removed is not found', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = memberOf($room);

    allowBackAs($creator, $room, $member)->assertNotFound();

    expect(membershipOf($room, $member)->status)->toBe('active');
});

test('the channel page lists removed members with Allow back for the creator only', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = kickedFrom($room);
    $lift = route('rooms.kick.lift', ['room' => $room->code, 'tag' => $member->tag]);

    $this->actingAs($creator)->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertSee('id="removed-members-modal"', false)
        ->assertSee($lift, false);

    $other = memberOf($room);
    $this->actingAs($other)->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertDontSee('id="removed-members-modal"', false);
});

test('allowing back from the channel page returns to the channel', function () {
    $creator = User::factory()->create();
    $room = kickChannel($creator);
    $member = kickedFrom($room);

    $this->actingAs($creator)->withSession(["room_clearance_{$room->id}" => true])
        ->from(route('rooms.show', $room->code))
        ->delete(route('rooms.kick.lift', ['room' => $room->code, 'tag' => $member->tag]))
        ->assertRedirect(route('rooms.show', $room->code))
        ->assertSessionHas('status');

    expect(membershipOf($room, $member)->status)->toBe('declined');
});
