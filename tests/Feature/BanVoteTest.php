<?php

use App\Enums\AuditAction;
use App\Enums\AuditVisibility;
use App\Enums\BanCaseStatus;
use App\Enums\BanDecision;
use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Enums\ReportStatus;
use App\Mail\BanCaseOpenedNotification;
use App\Models\AdminAuditLog;
use App\Models\BanCase;
use App\Models\BanVote;
use App\Models\Message;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\BanVoteService;
use App\Services\RankService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Open a case through the service, as the given admin.
 */
function openCaseAbout(User $subject, ?User $opener = null): BanCase
{
    return app(BanVoteService::class)->open($opener ?? User::factory()->adminOne()->create(), $subject, 'Repeated harassment');
}

function castBallot(User $voter, BanCase $case, bool $ban): void
{
    BanVote::create(['ban_case_id' => $case->id, 'voter_id' => $voter->id, 'ban' => $ban]);
}

function banChannel(User $creator): Room
{
    $room = Room::create([
        'code' => 'BAN-'.strtoupper(Str::random(4)),
        'type' => ChannelType::PrivateFreeEntry,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ]);
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    return $room;
}

/* -------------------------------------------------------------
 * Opening a case
 * ------------------------------------------------------------- */

test('an admin opens a ban vote: the subject is restricted and the other voters are emailed', function (Closure $opener) {
    Mail::fake();
    $adminOne = User::factory()->adminOne()->create();
    $adminTwo = User::factory()->adminTwo()->create();
    $openedBy = $opener($adminOne, $adminTwo);
    $subject = User::factory()->create(['tag' => 'pest_subject']);

    $this->actingAs($openedBy)
        ->post(route('moderation.bans.store'), ['tag' => '@pest_subject', 'reason' => 'Spam in five channels'])
        ->assertRedirect(route('moderation.bans.index'))
        ->assertSessionHas('status', 'Ban vote opened for @pest_subject. They are restricted until it closes.');

    $case = BanCase::sole();
    expect($case->status)->toBe(BanCaseStatus::Open);
    expect($case->closes_at->diffInHours(now()->addHours(72)))->toBeLessThan(1);
    expect($case->votes()->count())->toBe(0);
    expect($subject->isRestricted())->toBeTrue();
    expect(AdminAuditLog::where('action', AuditAction::BanCaseOpened)->sole()->visibility)->toBe(AuditVisibility::Moderation);

    $otherVoter = $openedBy->is($adminOne) ? $adminTwo : $adminOne;
    Mail::assertQueued(BanCaseOpenedNotification::class, fn ($mail) => $mail->hasTo($otherVoter->email));
    Mail::assertNotQueued(BanCaseOpenedNotification::class, fn ($mail) => $mail->hasTo($openedBy->email));
})->with([
    'Admin I' => [fn (User $adminOne, User $adminTwo) => $adminOne],
    'Admin II' => [fn (User $adminOne, User $adminTwo) => $adminTwo],
]);

test('a vote opened from a report marks the report reviewed', function () {
    $admin = User::factory()->adminOne()->create();
    $subject = User::factory()->create(['tag' => 'pest_subject']);
    $report = Report::factory()->create(['subject_user_id' => $subject->id]);

    $this->actingAs($admin)
        ->post(route('moderation.bans.store'), ['tag' => 'pest_subject', 'reason' => 'Confirmed', 'report_id' => $report->id])
        ->assertSessionHasNoErrors();

    expect(BanCase::sole()->report_id)->toBe($report->id);
    expect($report->fresh()->status)->toBe(ReportStatus::Reviewed);
    expect($report->fresh()->reviewed_by_user_id)->toBe($admin->id);
});

test('a vote cannot be opened from a report about someone else', function () {
    User::factory()->create(['tag' => 'pest_subject']);
    $report = Report::factory()->create();

    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('moderation.bans.store'), ['tag' => 'pest_subject', 'reason' => 'x', 'report_id' => $report->id])
        ->assertSessionHasErrors('report_id');

    expect(BanCase::count())->toBe(0);
});

test('some members cannot be put to a vote', function (Closure $subject, string $error) {
    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('moderation.bans.store'), ['tag' => $subject()->tag, 'reason' => 'x'])
        ->assertSessionHasErrors(['tag' => $error]);
})->with([
    'Admin I' => [fn () => User::factory()->adminOne()->create(), 'Admin I and II cannot be put to a ban vote.'],
    'Admin II' => [fn () => User::factory()->adminTwo()->create(), 'Admin I and II cannot be put to a ban vote.'],
    'already banned' => [fn () => User::factory()->create(['banned_at' => now()]), 'This member is already banned.'],
    'already under a vote' => [fn () => tap(User::factory()->create(), fn (User $user) => openCaseAbout($user))->fresh(), 'A ban vote about this member is already open.'],
]);

test('only Admin I and II can see or use ban reviews', function (Rank $rank) {
    $member = User::factory()->rank($rank)->create();
    $case = openCaseAbout(User::factory()->create(['tag' => 'pest_subject']));

    $this->actingAs($member)->get(route('moderation.bans.index'))->assertForbidden();
    $this->actingAs($member)->post(route('moderation.bans.vote', $case), ['ban' => 1])->assertForbidden();
    $this->actingAs($member)->post(route('moderation.bans.store'), ['tag' => 'pest_subject', 'reason' => 'x'])->assertForbidden();

    expect($case->votes()->count())->toBe(0);
})->with([Rank::AdminThree, Rank::UserOne, Rank::UserTwo]);

test('the review page shows open votes and open reports to Admin II', function () {
    $subject = User::factory()->create(['tag' => 'pest_subject']);
    openCaseAbout($subject);
    Report::factory()->create(['subject_user_id' => User::factory()->create(['tag' => 'reported_one'])->id, 'details' => 'Sent threats']);

    $this->actingAs(User::factory()->adminTwo()->create())
        ->get(route('moderation.bans.index'))
        ->assertOk()
        ->assertSee('@pest_subject')
        ->assertSee('Repeated harassment')
        ->assertSee('@reported_one')
        ->assertSee('Sent threats')
        ->assertDontSee('Ban an Admin II');
});

/* -------------------------------------------------------------
 * Restriction while a vote is open (AU-6, A3)
 * ------------------------------------------------------------- */

dataset('actions a restricted member cannot take', [
    'post a message' => [fn (Room $room, Message $message) => test()->postJson(route('messages.store', $room->code), ['content' => 'Hi'])],
    'react' => [fn (Room $room, Message $message) => test()->postJson(route('messages.react', [$room->code, $message->id]), ['emoji' => '👍'])],
    'pin' => [fn (Room $room, Message $message) => test()->postJson(route('rooms.pin', [$room->code, $message->id]))],
    'create a channel' => [fn (Room $room, Message $message) => test()->postJson(route('channels.store'), ['title' => 'New', 'type' => 'prf'])],
    'change channel settings' => [fn (Room $room, Message $message) => test()->putJson(route('channels.update', $room->id), ['title' => 'Renamed', 'type' => 'prf'])],
    'report someone' => [fn (Room $room, Message $message) => test()->postJson(route('members.report', User::factory()->create()->tag), ['reason' => 'spam'])],
    'purge the channel' => [fn (Room $room, Message $message) => test()->postJson(route('rooms.nuke', $room->code))],
]);

test('a member under a ban vote is read-only', function (Closure $action) {
    $subject = User::factory()->create();
    $room = banChannel($subject);
    $message = Message::create(['room_id' => $room->id, 'user_id' => $subject->id, 'sender_session_id' => 's', 'sender_name' => $subject->name, 'content' => 'Before']);
    openCaseAbout($subject);

    $this->actingAs($subject)->withSession(["room_clearance_{$room->id}" => true]);
    $action($room, $message)
        ->assertForbidden()
        ->assertJsonPath('error', 'Your account is restricted pending an admin review.');

    expect(Room::find($room->id))->not->toBeNull();
    expect(Message::count())->toBe(1);
})->with('actions a restricted member cannot take');

test('a member under a ban vote can still read their channels', function () {
    $subject = User::factory()->create();
    $room = banChannel($subject);
    openCaseAbout($subject);

    $this->actingAs($subject)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('messages.index', $room->code))
        ->assertOk();
});

test('a member under a ban vote cannot change their tag', function () {
    $subject = User::factory()->create(['tag' => 'old_tag']);
    openCaseAbout($subject);

    $this->actingAs($subject)
        ->postJson(route('profile.update'), ['name' => $subject->name, 'email' => $subject->email, 'tag' => 'new_tag'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tag');

    expect($subject->fresh()->tag)->toBe('old_tag');
});

test('the subject is told only that a review is pending', function () {
    $subject = User::factory()->create();
    openCaseAbout($subject);

    $this->actingAs($subject)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Your account is restricted pending an admin review.')
        ->assertDontSee('Repeated harassment')
        ->assertDontSee('ban-reviews-link', false);
});

/* -------------------------------------------------------------
 * Voting and deciding (Q6, AU-7)
 * ------------------------------------------------------------- */

test('a voter can change their ballot while the vote is open', function () {
    $admin = User::factory()->adminTwo()->create();
    User::factory()->adminTwo()->create();
    $case = openCaseAbout(User::factory()->create());

    $this->actingAs($admin)->post(route('moderation.bans.vote', $case), ['ban' => 1])->assertSessionHas('status', 'Vote recorded.');
    $this->actingAs($admin)->post(route('moderation.bans.vote', $case), ['ban' => 0]);

    expect(BanVote::sole()->ban)->toBeFalse();
    expect(AdminAuditLog::where('action', AuditAction::BanVoteCast)->count())->toBe(2);
});

test('ballots are refused once voting has closed', function () {
    $admin = User::factory()->adminTwo()->create();
    $case = openCaseAbout(User::factory()->create());
    $this->travel(73)->hours();

    $this->actingAs($admin)->post(route('moderation.bans.vote', $case), ['ban' => 1])->assertSessionHasErrors('vote');

    expect(BanVote::count())->toBe(0);
});

test('a case is decided by its ballots', function (array $ballots, bool $banned, BanDecision $decision) {
    $adminOne = User::factory()->adminOne()->create();
    $adminTwos = User::factory()->adminTwo()->count(3)->create();
    $case = openCaseAbout(User::factory()->create(), $adminOne);

    foreach ($ballots as $voter => $ban) {
        castBallot($voter === 'one' ? $adminOne : $adminTwos[$voter], $case, $ban);
    }

    expect(app(BanVoteService::class)->decide($case))->toBe([$banned, $decision]);
})->with([
    'majority to ban' => [[0 => true, 1 => true, 2 => false], true, BanDecision::Majority],
    'majority not to ban' => [[0 => true, 1 => false, 2 => false], false, BanDecision::Majority],
    'Admin I breaks a tie to ban' => [['one' => true, 0 => false], true, BanDecision::TieBreak],
    'Admin I breaks a tie not to ban' => [['one' => false, 0 => true], false, BanDecision::TieBreak],
    'tie without Admin I' => [[0 => true, 1 => false], false, BanDecision::TiedWithoutTieBreak],
    'no ballots' => [[], false, BanDecision::NoBallots],
]);

test('a case closes after 72 hours and not before', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    User::factory()->adminTwo()->create();
    $subject = User::factory()->create();
    $case = openCaseAbout($subject);
    castBallot($adminTwo, $case, true);

    $this->travel(71)->hours();
    $this->artisan('vesper:close-ban-cases')->assertSuccessful();
    expect($case->fresh()->isOpen())->toBeTrue();

    $this->travel(2)->hours();
    $this->artisan('vesper:close-ban-cases')->assertSuccessful();

    expect($case->fresh()->status)->toBe(BanCaseStatus::Banned);
    expect($case->fresh()->decision)->toBe(BanDecision::Majority);
    expect($subject->fresh()->isBanned())->toBeTrue();
    expect($subject->fresh()->isRestricted())->toBeFalse();
    expect(AdminAuditLog::where('action', AuditAction::BanCaseClosed)->exists())->toBeTrue();
});

test('a cleared member is no longer restricted', function () {
    $subject = User::factory()->create();
    $case = openCaseAbout($subject);
    $this->travel(73)->hours();

    app(BanVoteService::class)->closeDue();

    expect($case->fresh()->status)->toBe(BanCaseStatus::Cleared);
    expect($subject->fresh()->isRestricted())->toBeFalse();
    expect($subject->fresh()->isBanned())->toBeFalse();
});

test('with no Admin II, Admin I\'s ballot decides at once', function (bool $ban, BanCaseStatus $status) {
    $adminOne = User::factory()->adminOne()->create();
    $subject = User::factory()->create();
    $case = openCaseAbout($subject, $adminOne);

    $this->actingAs($adminOne)
        ->post(route('moderation.bans.vote', $case), ['ban' => (int) $ban])
        ->assertSessionHas('status', 'Vote recorded. The case is decided.');

    expect($case->fresh()->status)->toBe($status);
    expect($case->fresh()->decision)->toBe(BanDecision::AdminOneAlone);
})->with([
    'ban' => [true, BanCaseStatus::Banned],
    'don\'t ban' => [false, BanCaseStatus::Cleared],
]);

/* -------------------------------------------------------------
 * Banning
 * ------------------------------------------------------------- */

test('a ban revokes sessions and passkeys', function () {
    config(['session.driver' => 'database']);
    $subject = User::factory()->create(['remember_token' => 'remember-me']);
    enrollPasskey($subject);
    DB::table('sessions')->insert(['id' => 'banned-session', 'user_id' => $subject->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    $adminOne = User::factory()->adminOne()->create();
    $case = openCaseAbout($subject, $adminOne);

    app(BanVoteService::class)->vote($adminOne, $case, true);

    expect($subject->fresh()->isBanned())->toBeTrue();
    expect($subject->fresh()->remember_token)->toBeNull();
    expect(WebAuthnCredential::where('user_id', $subject->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $subject->id)->exists())->toBeFalse();
    expect(AdminAuditLog::where('action', AuditAction::MemberBanned)->where('subject_user_id', $subject->id)->exists())->toBeTrue();
});

test('a banned member is signed out on their next request', function () {
    $member = User::factory()->create();
    $this->actingAs($member)->get(route('profile.show'))->assertOk();

    $member->forceFill(['banned_at' => now()])->save();

    $this->get(route('profile.show'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['login' => 'This account has been banned from Vesper.']);
    $this->assertGuest();
});

test('a banned member cannot sign in with their password', function () {
    User::factory()->create(['email' => 'banned@example.com', 'password' => 'secret-password', 'banned_at' => now()]);

    $this->post(route('login.post'), ['login' => 'banned@example.com', 'password' => 'secret-password'])
        ->assertRedirect();
    $this->get(route('profile.show'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('banned members do not count toward a channel creator\'s rank', function () {
    $creator = User::factory()->create();
    $rooms = collect(range(1, 3))->map(fn () => channelOwnedBy($creator));
    expect(app(RankService::class)->recompute($creator))->toBe(Rank::UserOne);

    $rooms->first()->members()->where('users.id', '!=', $creator->id)->first()->forceFill(['banned_at' => now()])->save();

    expect(app(RankService::class)->recompute($creator))->toBe(Rank::UserTwo);
});

/* -------------------------------------------------------------
 * Admin II is banned directly by Admin I
 * ------------------------------------------------------------- */

test('Admin I bans an Admin II directly without a vote', function () {
    $adminOne = User::factory()->adminOne()->create();
    $adminTwo = User::factory()->adminTwo()->create(['tag' => 'admin_two']);

    $this->actingAs($adminOne)
        ->post(route('moderation.bans.direct'), ['tag' => 'admin_two', 'reason' => 'Leaked member data', 'confirm' => '1'])
        ->assertSessionHas('status', '@admin_two is banned.');

    $case = BanCase::sole();
    expect($case->status)->toBe(BanCaseStatus::Banned);
    expect($case->decision)->toBe(BanDecision::Direct);
    expect($adminTwo->fresh()->isBanned())->toBeTrue();
});

test('a direct ban needs confirmation and applies only to Admin II', function (array $payload, string $field) {
    User::factory()->adminTwo()->create(['tag' => 'admin_two']);
    User::factory()->create(['tag' => 'plain_member']);

    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('moderation.bans.direct'), $payload)
        ->assertSessionHasErrors($field);

    expect(User::whereNotNull('banned_at')->count())->toBe(0);
})->with([
    'not confirmed' => [['tag' => 'admin_two', 'reason' => 'x'], 'confirm'],
    'not an Admin II' => [['tag' => 'plain_member', 'reason' => 'x', 'confirm' => '1'], 'tag'],
]);

test('Admin II cannot ban another Admin II directly', function () {
    $target = User::factory()->adminTwo()->create(['tag' => 'admin_two']);

    $this->actingAs(User::factory()->adminTwo()->create())
        ->post(route('moderation.bans.direct'), ['tag' => 'admin_two', 'reason' => 'x', 'confirm' => '1'])
        ->assertForbidden();

    expect($target->fresh()->isBanned())->toBeFalse();
});
