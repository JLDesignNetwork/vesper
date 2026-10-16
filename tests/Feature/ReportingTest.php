<?php

use App\Enums\AuditAction;
use App\Enums\ChannelType;
use App\Enums\ReportOrigin;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\AdminAuditLog;
use App\Models\Message;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

function reportChannel(User $creator, ChannelType $type = ChannelType::PrivateFreeEntry): Room
{
    $room = Room::create([
        'code' => 'RPT-'.strtoupper(Str::random(4)),
        'type' => $type,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ]);
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    return $room;
}

test('a member reports another member from a channel message', function () {
    $reporter = User::factory()->create();
    $subject = User::factory()->create(['tag' => 'pest_subject']);
    $room = reportChannel($reporter);
    $message = Message::create(['room_id' => $room->id, 'user_id' => $subject->id, 'sender_session_id' => 's', 'sender_name' => $subject->name, 'content' => 'Buy now']);

    $this->actingAs($reporter)
        ->postJson(route('members.report', 'pest_subject'), [
            'reason' => 'spam',
            'details' => 'Posts the same link every hour.',
            'room' => $room->code,
            'message_id' => $message->id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Thanks. Your report about @pest_subject was sent to the moderators.');

    $report = Report::sole();
    expect($report->reporter_id)->toBe($reporter->id);
    expect($report->subject_user_id)->toBe($subject->id);
    expect($report->room_id)->toBe($room->id);
    expect($report->message_id)->toBe($message->id);
    expect($report->origin)->toBe(ReportOrigin::Direct);
    expect($report->reason)->toBe(ReportReason::Spam);
    expect($report->status)->toBe(ReportStatus::Open);
    expect($report->details)->toBe('Posts the same link every hour.');
});

test('reports are visible to Admin I and II in the audit log, and to no one else', function () {
    $reporter = User::factory()->create();
    User::factory()->create(['tag' => 'pest_subject']);
    $this->actingAs($reporter)->postJson(route('members.report', 'pest_subject'), ['reason' => 'harassment'])->assertOk();

    $visibleTo = fn (User $viewer) => AdminAuditLog::visibleTo($viewer)->where('action', AuditAction::ReportFiled)->count();

    expect($visibleTo(User::factory()->adminOne()->create()))->toBe(1);
    expect($visibleTo(User::factory()->adminTwo()->create()))->toBe(1);
    expect($visibleTo($reporter))->toBe(0);
});

test('a report needs a known reason', function (array $payload) {
    User::factory()->create(['tag' => 'pest_subject']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('members.report', 'pest_subject'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(Report::count())->toBe(0);
})->with([
    'missing' => [[]],
    'unknown' => [['reason' => 'boredom']],
]);

test('a reported message must be the subject\'s message in the named channel', function (Closure $messageFor) {
    $reporter = User::factory()->create();
    $subject = User::factory()->create(['tag' => 'pest_subject']);
    $room = reportChannel($reporter);

    $this->actingAs($reporter)
        ->postJson(route('members.report', 'pest_subject'), ['reason' => 'spam', 'room' => $room->code, 'message_id' => $messageFor($room, $subject, $reporter)->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
})->with([
    'someone else\'s message' => [fn (Room $room, User $subject, User $reporter) => Message::create(['room_id' => $room->id, 'user_id' => $reporter->id, 'sender_session_id' => 's', 'sender_name' => 'x', 'content' => 'Mine'])],
    'a message in another channel' => [fn (Room $room, User $subject, User $reporter) => Message::create(['room_id' => reportChannel($reporter)->id, 'user_id' => $subject->id, 'sender_session_id' => 's', 'sender_name' => 'x', 'content' => 'Elsewhere'])],
]);

test('a private channel the reporter cannot see is not attached to the report', function () {
    User::factory()->create(['tag' => 'pest_subject']);
    $hidden = reportChannel(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->postJson(route('members.report', 'pest_subject'), ['reason' => 'other', 'room' => $hidden->code])
        ->assertOk();

    expect(Report::sole()->room_id)->toBeNull();
});

test('members cannot report themselves', function () {
    $member = User::factory()->create(['tag' => 'pest_self']);

    $this->actingAs($member)->postJson(route('members.report', 'pest_self'), ['reason' => 'spam'])->assertUnprocessable();

    expect(Report::count())->toBe(0);
});

test('signed-out visitors cannot report', function () {
    User::factory()->create(['tag' => 'pest_subject']);

    $this->post(route('members.report', 'pest_subject'), ['reason' => 'spam'])->assertRedirect(route('login'));

    expect(Report::count())->toBe(0);
});
