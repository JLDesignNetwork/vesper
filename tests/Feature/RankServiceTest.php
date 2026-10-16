<?php

use App\Enums\AuditAction;
use App\Enums\Rank;
use App\Models\AdminAuditLog;
use App\Models\Room;
use App\Models\User;
use App\Services\RankService;
use Illuminate\Support\Facades\URL;

test('a member earns the rank their qualifying channel count reaches', function (int $qualifyingChannels, Rank $expectedRank) {
    $owner = User::factory()->create();
    for ($i = 0; $i < $qualifyingChannels; $i++) {
        channelOwnedBy($owner);
    }

    $rank = app(RankService::class)->recompute($owner);

    expect($rank)->toBe($expectedRank);
    expect($owner->fresh()->rank)->toBe($expectedRank);
})->with([
    'five channels earn Admin III' => [5, Rank::AdminThree],
    'four channels earn User I' => [4, Rank::UserOne],
    'three channels earn User I' => [3, Rank::UserOne],
    'two channels stay User II' => [2, Rank::UserTwo],
    'no channels stay User II' => [0, Rank::UserTwo],
]);

test('a channel that misses a qualifying rule does not count toward rank', function (Closure $makeDisqualifiedChannel) {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $makeDisqualifiedChannel($owner);

    expect(app(RankService::class)->recompute($owner))->toBe(Rank::UserTwo);
})->with([
    'only nine verified members besides the creator' => fn (User $owner) => channelOwnedBy($owner, verifiedMembers: 9),
    'last message eleven days ago' => fn (User $owner) => channelOwnedBy($owner, lastMessageAt: now()->subDays(11)),
    'channel has expired' => fn (User $owner) => channelOwnedBy($owner, roomAttributes: ['expires_at' => now()->subMinute()]),
    'channel is archived' => fn (User $owner) => channelOwnedBy($owner, roomAttributes: ['status' => 'archived']),
    'tenth member has not verified their email' => function (User $owner) {
        $room = channelOwnedBy($owner, verifiedMembers: 9);
        $unverified = User::factory()->unverified()->create();
        $room->belongsToMany(User::class, 'room_user')->attach($unverified->id, ['role' => 'member', 'status' => 'active']);
    },
    'tenth member has only been invited' => function (User $owner) {
        $room = channelOwnedBy($owner, verifiedMembers: 9);
        $room->inviteUser(User::factory()->create());
    },
]);

test('a channel whose last message is nine days old still qualifies', function () {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    channelOwnedBy($owner, lastMessageAt: now()->subDays(9));

    expect(app(RankService::class)->recompute($owner))->toBe(Rank::UserOne);
});

test('appointed ranks are never changed by a recompute', function (Rank $appointedRank) {
    $owner = User::factory()->rank($appointedRank)->create();

    expect(app(RankService::class)->recompute($owner))->toBe($appointedRank);
    expect($owner->fresh()->rank)->toBe($appointedRank);
    expect(AdminAuditLog::count())->toBe(0);
})->with([
    'Admin I' => [Rank::AdminOne],
    'Admin II' => [Rank::AdminTwo],
]);

test('a rank change is recorded in the audit log visible only to admin one', function () {
    $owner = User::factory()->create();
    foreach (range(1, 3) as $i) {
        channelOwnedBy($owner);
    }

    app(RankService::class)->recompute($owner);

    $entry = AdminAuditLog::sole();
    expect($entry->action)->toBe(AuditAction::RankChanged);
    expect($entry->subject_user_id)->toBe($owner->id);
    expect($entry->details)->toBe(['from' => 'user_two', 'to' => 'user_one', 'reason' => 'recompute']);
    expect(AdminAuditLog::visibleTo(User::factory()->adminTwo()->create())->count())->toBe(0);
});

test('a recompute that keeps the same rank writes no audit entry', function () {
    $owner = User::factory()->create();

    app(RankService::class)->recompute($owner);

    expect(AdminAuditLog::count())->toBe(0);
    expect($owner->fresh()->rank_computed_at)->not->toBeNull();
});

test('adding the tenth verified member promotes the channel owner immediately', function () {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner, verifiedMembers: 9);

    $room->addMember(User::factory()->create());

    expect($owner->fresh()->rank)->toBe(Rank::UserOne);
});

test('archiving a channel demotes its owner immediately', function () {
    $admin = User::factory()->adminOne()->create();
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner);
    app(RankService::class)->recompute($owner);
    expect($owner->fresh()->rank)->toBe(Rank::UserOne);

    $this->actingAs($admin)->post(route('admin.channels.toggle', $room->id))
        ->assertRedirect(route('admin.channels.index'));

    expect($room->fresh()->status)->toBe('archived');
    expect($owner->fresh()->rank)->toBe(Rank::UserTwo);
});

test('accepting a direct invitation promotes the channel owner immediately', function () {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner, verifiedMembers: 9);
    $invitee = User::factory()->create();
    $room->inviteUser($invitee, $owner->id);

    $this->actingAs($invitee)->post(route('channels.invites.accept', $room->id))
        ->assertRedirect(route('profile.show'));

    expect($owner->fresh()->rank)->toBe(Rank::UserOne);
});

test('a member verifying their email promotes the owner of a channel they belong to', function () {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner, verifiedMembers: 9);
    $member = User::factory()->unverified()->create();
    $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active']);
    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $member->id, 'hash' => sha1($member->email)]);

    $this->get($link);

    expect($owner->fresh()->rank)->toBe(Rank::UserOne);
});

test('deleting a member demotes the owner of a channel that drops below ten members', function () {
    $admin = User::factory()->adminOne()->create();
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner);
    app(RankService::class)->recompute($owner);
    $leavingMember = $room->members()->where('users.id', '!=', $owner->id)->first();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $leavingMember->id))
        ->assertRedirect(route('admin.dashboard'));

    expect($owner->fresh()->rank)->toBe(Rank::UserTwo);
});

test('the recompute command updates every member with a computed rank', function () {
    $risingOwner = User::factory()->create();
    foreach (range(1, 5) as $i) {
        channelOwnedBy($risingOwner);
    }
    $fallenOwner = User::factory()->rank(Rank::UserOne)->create();
    $adminTwo = User::factory()->adminTwo()->create();

    $this->artisan('vesper:recompute-ranks')
        ->expectsOutput('Recomputed ranks. 2 member(s) changed rank.')
        ->assertSuccessful();

    expect($risingOwner->fresh()->rank)->toBe(Rank::AdminThree);
    expect($fallenOwner->fresh()->rank)->toBe(Rank::UserTwo);
    expect($adminTwo->fresh()->rank)->toBe(Rank::AdminTwo);
});

test('a channel burning out on its timer demotes its owner', function () {
    $owner = User::factory()->create();
    channelOwnedBy($owner);
    channelOwnedBy($owner);
    $room = channelOwnedBy($owner);
    app(RankService::class)->recompute($owner);
    $room->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('vesper:channel-burn-check')->assertSuccessful();

    expect(Room::find($room->id)->status)->toBe('destroyed');
    expect($owner->fresh()->rank)->toBe(Rank::UserTwo);
});
