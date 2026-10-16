<?php

use App\Enums\AuditAction;
use App\Enums\AuditVisibility;
use App\Enums\BanCaseKind;
use App\Enums\BanCaseStatus;
use App\Enums\BanDecision;
use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Enums\ReportStatus;
use App\Mail\BanCaseOpenedNotification;
use App\Mail\BanLiftedNotification;
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

test('an Admin III under a ban vote cannot change their channels from the admin panel', function () {
    $adminThree = User::factory()->rank(Rank::AdminThree)->create();
    $room = banChannel($adminThree);
    openCaseAbout($adminThree);

    $this->actingAs($adminThree)->deleteJson(route('admin.channels.destroy', ['id' => $room->id]))->assertForbidden();
    $this->actingAs($adminThree)->postJson(route('admin.channels.invite', ['id' => $room->id]), ['max_uses' => 1])->assertForbidden();
    $this->actingAs($adminThree)->putJson(route('admin.channels.update', ['id' => $room->id]), ['title' => 'x', 'allowed_languages' => ['en']])->assertForbidden();

    expect(Room::find($room->id))->not->toBeNull();
});

/* -------------------------------------------------------------
 * Lifting a ban (owner decision Q13)
 * ------------------------------------------------------------- */

function liftVoteAbout(User $subject, ?User $opener = null): BanCase
{
    return app(BanVoteService::class)->openLift($opener ?? User::factory()->adminOne()->create(), $subject, 'Banned by mistake');
}

test('an admin opens a vote to lift a ban: the member stays banned and the other voters are emailed', function () {
    Mail::fake();
    $adminOne = User::factory()->adminOne()->create();
    $adminTwo = User::factory()->adminTwo()->create();
    $subject = User::factory()->create(['tag' => 'pest_banned', 'banned_at' => now()]);

    $this->actingAs($adminTwo)
        ->post(route('moderation.bans.lift'), ['tag' => '@pest_banned', 'reason' => 'The reports were filed in error'])
        ->assertRedirect(route('moderation.bans.index'))
        ->assertSessionHas('status', 'Vote to lift the ban on @pest_banned opened. They stay banned until it is decided.');

    $case = BanCase::sole();
    expect($case->kind)->toBe(BanCaseKind::Lift);
    expect($case->status)->toBe(BanCaseStatus::Open);
    expect($case->closes_at->diffInHours(now()->addHours(72)))->toBeLessThan(1);
    expect($subject->fresh()->isBanned())->toBeTrue();
    expect($subject->fresh()->isRestricted())->toBeFalse();
    expect(AdminAuditLog::where('action', AuditAction::BanCaseOpened)->sole()->details)->toMatchArray(['kind' => 'lift']);

    Mail::assertQueued(BanCaseOpenedNotification::class, fn ($mail) => $mail->hasTo($adminOne->email)
        && $mail->getRendered()['subject'] === 'Vote to lift a ban: @pest_banned');
    Mail::assertNotQueued(BanCaseOpenedNotification::class, fn ($mail) => $mail->hasTo($adminTwo->email));
});

test('a lift vote needs a banned member with no other open vote', function (Closure $subject, string $error) {
    $this->actingAs(User::factory()->adminOne()->create())
        ->post(route('moderation.bans.lift'), ['tag' => $subject()->tag, 'reason' => 'x'])
        ->assertSessionHasErrors(['tag' => $error]);

    expect(BanCase::ofKind(BanCaseKind::Lift)->count())->toBeLessThanOrEqual(1);
})->with([
    'not banned' => [fn () => User::factory()->create(), 'This member is not banned.'],
    'lift vote already open' => [fn () => tap(User::factory()->create(['banned_at' => now()]), fn (User $user) => liftVoteAbout($user)), 'A vote to lift this member\'s ban is already open.'],
]);

test('only Admin I and II can open a lift vote', function (Rank $rank) {
    User::factory()->create(['tag' => 'pest_banned', 'banned_at' => now()]);

    $this->actingAs(User::factory()->rank($rank)->create())
        ->post(route('moderation.bans.lift'), ['tag' => 'pest_banned', 'reason' => 'x'])
        ->assertForbidden();

    expect(BanCase::count())->toBe(0);
})->with([Rank::AdminThree, Rank::UserOne, Rank::UserTwo]);

test('a lift vote is decided by its ballots, and the ban stays without a decision', function (array $ballots, bool $banned, BanDecision $decision) {
    $adminOne = User::factory()->adminOne()->create();
    $adminTwos = User::factory()->adminTwo()->count(3)->create();
    $case = liftVoteAbout(User::factory()->create(['banned_at' => now()]), $adminOne);

    foreach ($ballots as $voter => $keepBan) {
        castBallot($voter === 'one' ? $adminOne : $adminTwos[$voter], $case, $keepBan);
    }

    expect(app(BanVoteService::class)->decide($case))->toBe([$banned, $decision]);
})->with([
    'majority to lift' => [[0 => false, 1 => false, 2 => true], false, BanDecision::Majority],
    'majority to keep' => [[0 => false, 1 => true, 2 => true], true, BanDecision::Majority],
    'Admin I breaks a tie to lift' => [['one' => false, 0 => true], false, BanDecision::TieBreak],
    'Admin I breaks a tie to keep' => [['one' => true, 0 => false], true, BanDecision::TieBreak],
    'tie without Admin I keeps the ban' => [[0 => true, 1 => false], true, BanDecision::TiedWithoutTieBreak],
    'no ballots keeps the ban' => [[], true, BanDecision::NoBallots],
]);

test('a lift vote decided to lift ends the ban, keeps the rank and emails the member', function () {
    Mail::fake();
    [$first, $second] = User::factory()->adminTwo()->count(2)->create()->all();
    $subject = User::factory()->rank(Rank::UserOne)->create(['email' => 'lifted@example.com', 'password' => 'secret-password', 'banned_at' => now()]);
    $case = liftVoteAbout($subject);
    castBallot($first, $case, false);
    castBallot($second, $case, false);

    $this->travel(73)->hours();
    $this->artisan('vesper:close-ban-cases')->assertSuccessful();

    expect($case->fresh()->status)->toBe(BanCaseStatus::Lifted);
    expect($subject->fresh()->isBanned())->toBeFalse();
    expect($subject->fresh()->rank)->toBe(Rank::UserOne);
    expect(AdminAuditLog::where('action', AuditAction::BanLifted)->where('subject_user_id', $subject->id)->sole()->visibility)->toBe(AuditVisibility::Moderation);
    Mail::assertQueued(BanLiftedNotification::class, fn ($mail) => $mail->hasTo('lifted@example.com')
        && $mail->getRendered()['subject'] === 'Your Vesper account is active again');

    $this->post(route('login.post'), ['login' => 'lifted@example.com', 'password' => 'secret-password']);
    $this->get(route('profile.show'))->assertOk();
    $this->assertAuthenticatedAs($subject->fresh());
});

test('a lift vote that keeps the ban changes nothing', function () {
    Mail::fake();
    $subject = User::factory()->create(['banned_at' => now()->subDay()]);
    $case = liftVoteAbout($subject);
    $this->travel(73)->hours();

    app(BanVoteService::class)->closeDue();

    expect($case->fresh()->status)->toBe(BanCaseStatus::Upheld);
    expect($case->fresh()->decision)->toBe(BanDecision::NoBallots);
    expect($subject->fresh()->isBanned())->toBeTrue();
    expect(AdminAuditLog::where('action', AuditAction::BanLifted)->exists())->toBeFalse();
    expect(AdminAuditLog::where('action', AuditAction::MemberBanned)->exists())->toBeFalse();
    Mail::assertNotQueued(BanLiftedNotification::class);
});

test('with no Admin II, Admin I\'s ballot decides a lift vote at once', function (int $keepBan, BanCaseStatus $status, bool $banned) {
    $adminOne = User::factory()->adminOne()->create();
    $subject = User::factory()->create(['banned_at' => now()]);
    $case = liftVoteAbout($subject, $adminOne);

    $this->actingAs($adminOne)
        ->post(route('moderation.bans.vote', $case), ['ban' => $keepBan])
        ->assertSessionHas('status', 'Vote recorded. The case is decided.');

    expect($case->fresh()->status)->toBe($status);
    expect($case->fresh()->decision)->toBe(BanDecision::AdminOneAlone);
    expect($subject->fresh()->isBanned())->toBe($banned);
})->with([
    'lift' => [0, BanCaseStatus::Lifted, false],
    'keep' => [1, BanCaseStatus::Upheld, true],
]);

test('a banned Admin II gets their ban lifted only by a vote, and keeps their rank', function () {
    $adminOne = User::factory()->adminOne()->create();
    $adminTwo = User::factory()->adminTwo()->create(['tag' => 'pest_admin_two']);
    app(BanVoteService::class)->banAdminTwo($adminOne, $adminTwo, 'Leaked a private channel');

    $case = liftVoteAbout($adminTwo->fresh(), $adminOne);
    app(BanVoteService::class)->vote($adminOne, $case, false);

    expect($case->fresh()->status)->toBe(BanCaseStatus::Lifted);
    expect($adminTwo->fresh()->isBanned())->toBeFalse();
    expect($adminTwo->fresh()->rank)->toBe(Rank::AdminTwo);
});

test('the review page lists banned members and shows lift votes', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    User::factory()->create(['tag' => 'pest_banned', 'banned_at' => now()]);
    $underVote = User::factory()->create(['tag' => 'pest_under_vote', 'banned_at' => now()]);
    $case = liftVoteAbout($underVote);
    castBallot($adminTwo, $case, false);

    $this->actingAs($adminTwo)
        ->get(route('moderation.bans.index'))
        ->assertOk()
        ->assertSee('Banned members')
        ->assertSee('@pest_banned')
        ->assertSee('Open vote to lift ban')
        ->assertSee('A vote to lift this ban is open.')
        ->assertSee('Lift ban 1 · Keep ban 0')
        ->assertSee('You voted to lift the ban')
        ->assertSeeInOrder(['name="ban" value="0"', '>Lift ban</button>', 'name="ban" value="1"', '>Keep ban</button>'], false);
});

test('recent decisions show whether a ban was lifted or kept', function () {
    $lifted = User::factory()->create(['tag' => 'pest_lifted', 'banned_at' => now()]);
    $kept = User::factory()->create(['tag' => 'pest_kept', 'banned_at' => now()]);
    $adminOne = User::factory()->adminOne()->create();
    app(BanVoteService::class)->vote($adminOne, liftVoteAbout($lifted, $adminOne), false);
    app(BanVoteService::class)->vote($adminOne, liftVoteAbout($kept, $adminOne), true);

    $this->actingAs($adminOne)
        ->get(route('moderation.bans.index'))
        ->assertOk()
        ->assertSeeInOrder(['Recent decisions', '@pest_kept', 'Ban kept'])
        ->assertSeeInOrder(['Recent decisions', '@pest_lifted', 'Ban lifted']);
});
