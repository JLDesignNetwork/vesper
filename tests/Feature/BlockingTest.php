<?php

use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Enums\ReportOrigin;
use App\Mail\NewMessageNotification;
use App\Models\AccessLog;
use App\Models\Message;
use App\Models\MessagePin;
use App\Models\MessageUserView;
use App\Models\Reaction;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\BlockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A free-entry channel owned by $creator with $members enrolled.
 */
function blockChannel(User $creator, User ...$members): Room
{
    $room = Room::create([
        'code' => 'BLK-'.strtoupper(Str::random(4)),
        'type' => ChannelType::PrivateFreeEntry,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ]);
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    foreach ($members as $member) {
        $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active']);
    }

    return $room;
}

function messageFrom(Room $room, User $sender, array $attributes = []): Message
{
    return Message::create(array_merge([
        'room_id' => $room->id,
        'user_id' => $sender->id,
        'sender_session_id' => 'session-'.$sender->id,
        'sender_name' => $sender->name,
        'content' => 'Message from '.$sender->name,
    ], $attributes));
}

function inChannel(User $user, Room $room)
{
    return test()->actingAs($user)->withSession(["room_clearance_{$room->id}" => true]);
}

function feedFor(User $user, Room $room)
{
    return inChannel($user, $room)->getJson(route('messages.index', $room->code));
}

/**
 * Store a block directly, through the service so caches stay consistent.
 */
function blocks(User $blocker, User $blocked): void
{
    app(BlockService::class)->block($blocker, $blocked);
}

/* -------------------------------------------------------------
 * Blocks hide content in both directions
 * ------------------------------------------------------------- */

dataset('block direction', [
    'viewer blocked the author' => [fn (User $viewer, User $author) => blocks($viewer, $author)],
    'author blocked the viewer' => [fn (User $viewer, User $author) => blocks($author, $viewer)],
]);

test('the message feed leaves out messages between blocked members', function (Closure $block) {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $bystander = User::factory()->create();
    $room = blockChannel($bystander, $viewer, $author);
    $hidden = messageFrom($room, $author);
    $visible = messageFrom($room, $bystander);
    $block($viewer, $author);

    $ids = collect(feedFor($viewer, $room)->assertOk()->json('messages'))->pluck('id');

    expect($ids->all())->toBe([$visible->id]);
    expect($ids)->not->toContain($hidden->id);
})->with('block direction');

test('blocked members do not appear in presence, reactions, reply previews or the pinned tray', function (Closure $block) {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['name' => 'Hidden Author']);
    $creator = User::factory()->create();
    $room = blockChannel($creator, $viewer, $author);
    $original = messageFrom($room, $author, ['content' => 'Secret original']);
    $reply = messageFrom($room, $creator, ['reply_to_id' => $original->id]);
    Reaction::create(['room_id' => $room->id, 'message_id' => $reply->id, 'user_id' => $author->id, 'session_id' => 'session-'.$author->id, 'emoji' => '🔥']);
    Reaction::create(['room_id' => $room->id, 'message_id' => $reply->id, 'user_id' => $creator->id, 'session_id' => 'session-'.$creator->id, 'emoji' => '🔥']);
    MessagePin::create(['room_id' => $room->id, 'message_id' => $original->id, 'pinned_by_user_id' => $creator->id]);
    AccessLog::create(['room_id' => $room->id, 'user_id' => $author->id, 'session_id' => 'session-'.$author->id, 'alias' => 'Hidden Author', 'ip_address' => '203.0.113.9', 'last_seen_at' => now()]);
    $block($viewer, $author);

    $feed = feedFor($viewer, $room)->assertOk();

    $replyPayload = collect($feed->json('messages'))->firstWhere('id', $reply->id);
    expect($replyPayload['reply_to'])->toBeNull();
    expect($replyPayload['reactions'])->toBe([['emoji' => '🔥', 'count' => 1, 'has_reacted' => false]]);
    expect($feed->json('pinned_messages'))->toBe([]);
    expect(collect($feed->json('active_operatives'))->pluck('alias'))->not->toContain('Hidden Author');
})->with('block direction');

test('a blocked member\'s tag is not highlighted as a mention', function (Closure $block) {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['tag' => 'hidden_one']);
    $creator = User::factory()->create(['tag' => 'chief']);
    $room = blockChannel($creator, $viewer, $author);
    messageFrom($room, $creator, ['content' => 'Thanks @hidden_one and @chief']);
    $block($viewer, $author);

    feedFor($viewer, $room)->assertOk()->assertJsonPath('mention_tags', ['chief']);
})->with('block direction');

test('member search hides blocked members in both directions', function (Closure $block) {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['name' => 'Rowan Hidden', 'tag' => 'rowan_hidden']);
    User::factory()->create(['name' => 'Rowan Visible', 'tag' => 'rowan_visible']);
    $block($viewer, $author);

    $tags = collect($this->actingAs($viewer)->getJson(route('members.search', ['q' => 'rowan']))->assertOk()->json('members'))->pluck('tag');

    expect($tags->all())->toBe(['rowan_visible']);
})->with('block direction');

test('the member card does not open for a blocked member', function (Closure $block) {
    $viewer = User::factory()->create();
    $author = User::factory()->create(['tag' => 'hidden_one']);
    $room = blockChannel(User::factory()->create(), $viewer, $author);
    $block($viewer, $author);

    inChannel($viewer, $room)
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'tag' => 'hidden_one']))
        ->assertOk()
        ->assertJsonPath('found', false);
    inChannel($viewer, $room)
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => $author->name]))
        ->assertJsonPath('found', false);
})->with('block direction');

/* -------------------------------------------------------------
 * Blocks prevent contact
 * ------------------------------------------------------------- */

dataset('actions on a blocked member\'s message', [
    'reply' => [fn (Room $room, Message $message) => test()->postJson(route('messages.store', $room->code), ['content' => 'Reply', 'reply_to_id' => $message->id])],
    'react' => [fn (Room $room, Message $message) => test()->postJson(route('messages.react', [$room->code, $message->id]), ['emoji' => '👍'])],
    'download' => [fn (Room $room, Message $message) => test()->getJson(route('messages.attachment', [$room->code, $message->id]))],
]);

test('members cannot reply to, react to or download a blocked member\'s message', function (Closure $action) {
    Storage::fake('local');
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $room = blockChannel(User::factory()->create(), $viewer, $author);
    $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')->store("attachments/{$room->id}", 'local');
    $message = messageFrom($room, $author, ['attachment_path' => $path, 'attachment_name' => 'brief.pdf', 'attachment_mime' => 'application/pdf']);
    blocks($author, $viewer);

    inChannel($viewer, $room);
    $action($room, $message)->assertNotFound();

    expect(Reaction::where('message_id', $message->id)->exists())->toBeFalse();
    expect(Message::where('reply_to_id', $message->id)->exists())->toBeFalse();
})->with('actions on a blocked member\'s message');

test('a blocked member\'s message cannot be translated by id', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $room = blockChannel(User::factory()->create(), $viewer, $author);
    $message = messageFrom($room, $author);
    blocks($viewer, $author);

    inChannel($viewer, $room)
        ->postJson(route('messages.translate', $room->code), ['message_id' => $message->id, 'target_lang' => 'fr'])
        ->assertUnprocessable();
});

test('a reply can only point at a message in the same channel', function () {
    $member = User::factory()->create();
    $room = blockChannel(User::factory()->create(), $member);
    $elsewhere = messageFrom(blockChannel(User::factory()->create()), User::factory()->create(), ['content' => 'Other channel secret']);

    inChannel($member, $room)
        ->postJson(route('messages.store', $room->code), ['content' => 'Reply', 'reply_to_id' => $elsewhere->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reply_to_id');
});

test('email notifications skip members who blocked the sender', function () {
    Mail::fake();
    $sender = User::factory()->create();
    $blocker = User::factory()->create(['email_notifications' => true]);
    $subscriber = User::factory()->create(['email_notifications' => true]);
    $room = blockChannel(User::factory()->create(), $sender, $blocker, $subscriber);
    messageFrom($room, $blocker);
    messageFrom($room, $subscriber);
    blocks($blocker, $sender);

    inChannel($sender, $room)->postJson(route('messages.store', $room->code), ['content' => 'News'])->assertOk();

    Mail::assertQueued(NewMessageNotification::class, fn ($mail) => $mail->hasTo($subscriber->email));
    Mail::assertNotQueued(NewMessageNotification::class, fn ($mail) => $mail->hasTo($blocker->email));
});

test('a sender\'s self-destructing message expires without waiting for members who blocked them', function () {
    $sender = User::factory()->create();
    $reader = User::factory()->create();
    $blocker = User::factory()->create();
    $room = blockChannel(User::factory()->create(), $sender, $reader, $blocker);
    $message = messageFrom($room, $sender, ['ttl_seconds' => 10]);
    blocks($blocker, $sender);
    MessageUserView::create(['message_id' => $message->id, 'user_id' => $room->created_by_user_id, 'session_id' => 'creator', 'viewed_at' => now(), 'expires_at' => now()->addSeconds(10)]);

    feedFor($reader, $room)->assertOk();
    $this->travel(30)->seconds();

    $ids = collect(feedFor($sender, $room)->json('messages'))->pluck('id');
    expect($ids)->not->toContain($message->id);
});

/* -------------------------------------------------------------
 * Blocking and unblocking
 * ------------------------------------------------------------- */

test('a member blocks another by tag', function () {
    $blocker = User::factory()->create();
    $target = User::factory()->create(['tag' => 'pest_target']);

    $this->actingAs($blocker)
        ->postJson(route('members.block', 'pest_target'))
        ->assertOk()
        ->assertJsonPath('message', '@pest_target is blocked. You will no longer see each other in channels or search.');

    expect(UserBlock::where('blocker_id', $blocker->id)->where('blocked_id', $target->id)->exists())->toBeTrue();
    expect(Report::count())->toBe(0);
});

test('blocking Admin I or II files a report and answers exactly like a block', function (Rank $rank) {
    $member = User::factory()->create();
    $admin = User::factory()->rank($rank)->create(['tag' => 'pest_target']);

    $this->actingAs($member)
        ->postJson(route('members.block', 'pest_target'))
        ->assertOk()
        ->assertJsonPath('message', '@pest_target is blocked. You will no longer see each other in channels or search.');

    expect(UserBlock::count())->toBe(0);
    expect(Report::where('reporter_id', $member->id)->where('subject_user_id', $admin->id)->sole()->origin)->toBe(ReportOrigin::BlockAttempt);
})->with([Rank::AdminOne, Rank::AdminTwo]);

/* -------------------------------------------------------------
 * Admin III: blockable, except inside their own channels
 * ------------------------------------------------------------- */

test('an Admin III can be blocked outside their own channels', function () {
    $member = User::factory()->create();
    $adminThree = User::factory()->rank(Rank::AdminThree)->create(['tag' => 'pest_target']);
    $elsewhere = blockChannel(User::factory()->create(), $member, $adminThree);

    $this->actingAs($member)
        ->postJson(route('members.block', 'pest_target'), ['room' => $elsewhere->code])
        ->assertOk()
        ->assertJsonPath('message', '@pest_target is blocked. You will no longer see each other in channels or search.');

    expect(UserBlock::where('blocker_id', $member->id)->where('blocked_id', $adminThree->id)->exists())->toBeTrue();
    expect(Report::count())->toBe(0);
});

test('blocking an Admin III from inside their own channel files a report and answers exactly like a block', function () {
    $member = User::factory()->create();
    $adminThree = User::factory()->rank(Rank::AdminThree)->create(['tag' => 'pest_target']);
    $ownChannel = blockChannel($adminThree, $member);

    $this->actingAs($member)
        ->postJson(route('members.block', 'pest_target'), ['room' => $ownChannel->code])
        ->assertOk()
        ->assertJsonPath('message', '@pest_target is blocked. You will no longer see each other in channels or search.');

    expect(UserBlock::count())->toBe(0);
    expect(Report::sole()->origin)->toBe(ReportOrigin::BlockAttempt);
});

test('a block against an Admin III does not apply inside their own channel', function () {
    $member = User::factory()->create();
    $adminThree = User::factory()->rank(Rank::AdminThree)->create();
    $ownChannel = blockChannel($adminThree, $member);
    $elsewhere = blockChannel(User::factory()->create(), $member, $adminThree);
    $ownerMessage = messageFrom($ownChannel, $adminThree);
    $memberMessage = messageFrom($ownChannel, $member);
    $elsewhereMessage = messageFrom($elsewhere, $adminThree);
    blocks($member, $adminThree);

    expect(collect(feedFor($member, $ownChannel)->json('messages'))->pluck('id'))->toContain($ownerMessage->id);
    expect(collect(feedFor($adminThree, $ownChannel)->json('messages'))->pluck('id'))->toContain($memberMessage->id);
    expect(collect(feedFor($member, $elsewhere)->json('messages'))->pluck('id'))->not->toContain($elsewhereMessage->id);
});

test('a block placed by an Admin III still applies inside their own channel', function (bool $memberAlsoBlocked) {
    $member = User::factory()->create();
    $adminThree = User::factory()->rank(Rank::AdminThree)->create();
    $ownChannel = blockChannel($adminThree, $member);
    $ownerMessage = messageFrom($ownChannel, $adminThree);
    $memberMessage = messageFrom($ownChannel, $member);
    blocks($adminThree, $member);
    if ($memberAlsoBlocked) {
        blocks($member, $adminThree);
    }

    expect(collect(feedFor($member, $ownChannel)->json('messages'))->pluck('id'))->not->toContain($ownerMessage->id);
    expect(collect(feedFor($adminThree, $ownChannel)->json('messages'))->pluck('id'))->not->toContain($memberMessage->id);
})->with([
    'only the Admin III blocked' => [false],
    'both blocked each other' => [true],
]);

test('members cannot block themselves', function () {
    $member = User::factory()->create(['tag' => 'pest_self']);

    $this->actingAs($member)->postJson(route('members.block', 'pest_self'))->assertUnprocessable();

    expect(UserBlock::count())->toBe(0);
});

test('blocking an unknown tag is not found', function () {
    $this->actingAs(User::factory()->create())->postJson(route('members.block', 'nobody_here'))->assertNotFound();
});

test('signed-out visitors cannot block', function () {
    User::factory()->create(['tag' => 'pest_target']);

    $this->post(route('members.block', 'pest_target'))->assertRedirect(route('login'));

    expect(UserBlock::count())->toBe(0);
});

test('unblocking restores visibility but leaves the other member\'s block in place', function () {
    $first = User::factory()->create(['tag' => 'first_member']);
    $second = User::factory()->create(['tag' => 'second_member']);
    $room = blockChannel(User::factory()->create(), $first, $second);
    $message = messageFrom($room, $second);
    blocks($first, $second);
    blocks($second, $first);

    $this->actingAs($first)->deleteJson(route('members.unblock', 'second_member'))->assertOk();

    expect(UserBlock::where('blocker_id', $second->id)->exists())->toBeTrue();
    expect(collect(feedFor($first, $room)->json('messages'))->pluck('id'))->not->toContain($message->id);

    $this->actingAs($second)->deleteJson(route('members.unblock', 'first_member'))->assertOk();

    expect(collect(feedFor($first, $room)->json('messages'))->pluck('id'))->toContain($message->id);
});

test('the profile lists blocked members with an unblock form', function () {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create(['name' => 'Quiet Person', 'tag' => 'quiet_person']);
    blocks($blocker, $blocked);

    $this->actingAs($blocker)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Quiet Person')
        ->assertSee('@quiet_person')
        ->assertSee(route('members.unblock', 'quiet_person'), false);

    $this->actingAs($blocker)
        ->from(route('profile.show'))
        ->delete(route('members.unblock', 'quiet_person'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('status', '@quiet_person is unblocked.');

    expect(UserBlock::count())->toBe(0);
});
