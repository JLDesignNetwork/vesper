<?php

use App\Models\Message;
use App\Models\MessageUserView;
use App\Models\Reaction;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('clearance is required to react to messages', function () {
    $room = Room::create([
        'code' => 'TACT-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'sender_session_id' => 'sess-1',
        'sender_name' => 'Agent',
        'content' => 'Classified transmission',
    ]);

    $response = $this->postJson(route('messages.react', [
        'room' => 'TACT-01',
        'message' => $message->id,
    ]), ['emoji' => '👍']);

    $response->assertStatus(403);
});

test('a user with clearance can toggle emoji reactions on a message', function () {
    $user = User::factory()->create(['name' => 'Bravo']);

    $room = Room::create([
        'code' => 'TACT-02',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'sender_session_id' => 'sess-1',
        'sender_name' => 'Alpha',
        'content' => 'Target acquired.',
    ]);

    // First toggle: Add reaction 👍
    $response1 = $this->actingAs($user)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Bravo',
    ])->postJson(route('messages.react', [
        'room' => 'TACT-02',
        'message' => $message->id,
    ]), ['emoji' => '👍']);

    $response1->assertStatus(200);
    $response1->assertJsonPath('success', true);
    $response1->assertJsonPath('action', 'added');
    $response1->assertJsonPath('reactions.0.emoji', '👍');
    $response1->assertJsonPath('reactions.0.count', 1);
    $response1->assertJsonPath('reactions.0.has_reacted', true);

    $this->assertDatabaseHas('reactions', [
        'room_id' => $room->id,
        'message_id' => $message->id,
        'emoji' => '👍',
        'user_id' => $user->id,
    ]);

    // Second toggle with same user: Remove reaction 👍
    $response2 = $this->actingAs($user)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Bravo',
    ])->postJson(route('messages.react', [
        'room' => 'TACT-02',
        'message' => $message->id,
    ]), ['emoji' => '👍']);

    $response2->assertStatus(200);
    $response2->assertJsonPath('action', 'removed');
    $response2->assertJsonPath('reactions', []);

    $this->assertDatabaseMissing('reactions', [
        'room_id' => $room->id,
        'message_id' => $message->id,
        'emoji' => '👍',
    ]);
});

test('media streaming supports direct download with Content-Disposition attachment', function () {
    Storage::fake('local');

    $room = Room::create([
        'code' => 'TACT-MEDIA',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $file = UploadedFile::fake()->image('recon_satellite.jpg', 600, 400);

    $storeResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Analyst',
    ])->postJson(route('messages.store', ['room' => 'TACT-MEDIA']), [
        'content' => 'Reconnaissance Satellite Capture',
        'attachment' => $file,
    ]);

    $storeResponse->assertStatus(200);
    $message = Message::where('room_id', $room->id)->first();
    expect($message)->not->toBeNull();

    // Stream with ?download=1
    $downloadResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Director',
    ])->get(route('messages.attachment', [
        'room' => 'TACT-MEDIA',
        'message' => $message->id,
        'download' => '1',
    ]));

    $downloadResponse->assertStatus(200);
    $downloadResponse->assertHeader('Content-Disposition', 'attachment; filename="recon_satellite.jpg"');
});

test('messages can quote and reply to previous messages', function () {
    $room = Room::create([
        'code' => 'TACT-REPLY',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $parentMsg = Message::create([
        'room_id' => $room->id,
        'sender_session_id' => 'sess-parent',
        'sender_name' => 'Commander',
        'content' => 'Deploy team to sector 7 immediately.',
    ]);

    $replyResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Echo',
    ])->postJson(route('messages.store', ['room' => 'TACT-REPLY']), [
        'content' => 'En route. ETA 3 minutes.',
        'reply_to_id' => $parentMsg->id,
    ]);

    $replyResponse->assertStatus(200);
    $replyResponse->assertJsonPath('success', true);
    $replyResponse->assertJsonPath('message.reply_to.id', $parentMsg->id);
    $replyResponse->assertJsonPath('message.reply_to.sender_name', 'Commander');
    $replyResponse->assertJsonPath('message.reply_to.snippet', 'Deploy team to sector 7 immediately.');

    $storedReply = Message::where('room_id', $room->id)->where('reply_to_id', $parentMsg->id)->first();
    expect($storedReply)->not->toBeNull();
    expect($storedReply->content)->toBe('En route. ETA 3 minutes.');
    expect($storedReply->reply_to_id)->toBe($parentMsg->id);
});

test('messages with ttl_seconds auto-expire and are excluded from message stream after expiration', function () {
    $watcher = User::factory()->create(['name' => 'Watcher']);

    $room = Room::create([
        'code' => 'TACT-TTL',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    // Create a message expiring in 30 seconds
    $sendResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Ghost',
    ])->postJson(route('messages.store', ['room' => 'TACT-TTL']), [
        'content' => 'This message will self-destruct.',
        'ttl_seconds' => 30,
    ]);

    $sendResponse->assertStatus(200);
    $sendResponse->assertJsonPath('message.ttl_seconds', 30);
    $sendResponse->assertJsonPath('message.expires_at', null);

    $msg = Message::where('room_id', $room->id)->first();
    expect($msg->ttl_seconds)->toBe(30);
    expect($msg->expires_at)->toBeNull();

    // Query active stream as another operative ('Watcher'): viewing the message triggers the countdown!
    $streamResponse1 = $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Watcher',
    ])->getJson(route('messages.index', ['room' => 'TACT-TTL']));

    $streamResponse1->assertStatus(200);
    $streamResponse1->assertJsonCount(1, 'messages');
    $streamResponse1->assertJsonPath('messages.0.ttl_seconds', 30);
    expect($streamResponse1->json('messages.0.expires_at'))->not->toBeNull();

    // The operative's view record in DB has expires_at set in the future
    $view = MessageUserView::where('message_id', $msg->id)->first();
    expect($view)->not->toBeNull();
    expect($view->expires_at->isFuture())->toBeTrue();

    // Simulate time passing beyond expiration
    $view->update(['expires_at' => now()->subSecond()]);

    $streamResponse2 = $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Watcher',
    ])->getJson(route('messages.index', ['room' => 'TACT-TTL']));

    $streamResponse2->assertStatus(200);
    $streamResponse2->assertJsonCount(0, 'messages');
});

test('in a group chat each operative receives their own independent self-destruct timer on view', function () {
    $user1 = User::factory()->create(['name' => 'OperativeOne']);
    $user2 = User::factory()->create(['name' => 'OperativeTwo']);

    $room = Room::create([
        'code' => 'TACT-GROUP-TTL',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    // Ghost transmits a 30s self-destruct message
    $sendResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Ghost',
    ])->postJson(route('messages.store', ['room' => 'TACT-GROUP-TTL']), [
        'content' => 'Classified payload for all operatives.',
        'ttl_seconds' => 30,
    ]);

    $sendResponse->assertStatus(200);
    $msg = Message::where('room_id', $room->id)->first();

    // 1. OperativeOne logs in and views the message
    $viewResponse1 = $this->actingAs($user1)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-GROUP-TTL']));

    $viewResponse1->assertStatus(200);
    $viewResponse1->assertJsonCount(1, 'messages');
    expect($viewResponse1->json('messages.0.expires_at'))->not->toBeNull();

    // OperativeOne's personal view record has an expiration set
    $user1View = MessageUserView::where('message_id', $msg->id)->where('user_id', $user1->id)->first();
    expect($user1View)->not->toBeNull();

    // 2. Simulate OperativeOne's timer expiring
    $user1View->update(['expires_at' => now()->subSecond()]);

    // OperativeOne checks stream again: burned for OperativeOne!
    $expiredResponse1 = $this->actingAs($user1)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-GROUP-TTL']));
    $expiredResponse1->assertJsonCount(0, 'messages');

    // 3. OperativeTwo logs in later: message is still available with fresh timer for OperativeTwo!
    $viewResponse2 = $this->actingAs($user2)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-GROUP-TTL']));

    $viewResponse2->assertStatus(200);
    $viewResponse2->assertJsonCount(1, 'messages');
    $user2ExpiresAt = $viewResponse2->json('messages.0.expires_at');
    expect($user2ExpiresAt)->not->toBeNull();

    $user2View = MessageUserView::where('message_id', $msg->id)->where('user_id', $user2->id)->first();
    expect($user2View)->not->toBeNull();
    expect($user2View->expires_at->isFuture())->toBeTrue();

    // 4. OperativeTwo's timer expires
    $user2View->update(['expires_at' => now()->subSecond()]);
    $expiredResponse2 = $this->actingAs($user2)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-GROUP-TTL']));
    $expiredResponse2->assertJsonCount(0, 'messages');
});

test('self-destruct message expires for creator once all chat members have viewed and their timers have expired', function () {
    $creator = User::factory()->create(['name' => 'Creator']);
    $member1 = User::factory()->create(['name' => 'MemberOne']);
    $member2 = User::factory()->create(['name' => 'MemberTwo']);

    $room = Room::create([
        'code' => 'TACT-CREATOR-BURN',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ]);

    $room->addMember($creator, 'owner');
    $room->addMember($member1, 'member');
    $room->addMember($member2, 'member');

    // Creator posts a 30s self-destruct message
    $sendResponse = $this->actingAs($creator)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->postJson(route('messages.store', ['room' => 'TACT-CREATOR-BURN']), [
        'content' => 'Top secret directive for MemberOne and MemberTwo.',
        'ttl_seconds' => 30,
    ]);

    $sendResponse->assertStatus(200);
    $msg = Message::where('room_id', $room->id)->first();

    // Prior to any recipient views, creator sees message with pending timer
    $creatorCheck1 = $this->actingAs($creator)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));
    $creatorCheck1->assertJsonCount(1, 'messages');
    $creatorCheck1->assertJsonPath('messages.0.ttl_seconds', 30);
    $creatorCheck1->assertJsonPath('messages.0.expires_at', null);

    // MemberOne views the message
    $this->actingAs($member1)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));

    // MemberTwo has NOT viewed yet: Creator's message still waits (expires_at is null)
    $creatorCheck2 = $this->actingAs($creator)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));
    $creatorCheck2->assertJsonCount(1, 'messages');
    $creatorCheck2->assertJsonPath('messages.0.expires_at', null);

    // MemberTwo views the message
    $this->actingAs($member2)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));

    // Now all other members have viewed: Creator receives the synchronized countdown
    $creatorCheck3 = $this->actingAs($creator)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));
    $creatorCheck3->assertJsonCount(1, 'messages');
    expect($creatorCheck3->json('messages.0.expires_at'))->not->toBeNull();

    // Expire all member view timers
    MessageUserView::where('message_id', $msg->id)->update(['expires_at' => now()->subSecond()]);

    // Creator checks again: all other members expired, so message is burned and excluded for creator too!
    $creatorCheck4 = $this->actingAs($creator)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-CREATOR-BURN']));
    $creatorCheck4->assertJsonCount(0, 'messages');
});

test('channel owners or admins can pin and unpin briefing messages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'member']);

    $room = Room::create([
        'code' => 'TACT-PIN',
        'title' => 'Command Operation',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $admin->id,
    ]);

    $briefingMsg = Message::create([
        'room_id' => $room->id,
        'sender_session_id' => 'sess-briefing',
        'sender_name' => 'Commander',
        'content' => 'Rules of engagement: Strict stealth protocol in effect.',
    ]);

    // Regular member without clearance or admin cannot pin
    $failResponse = $this->actingAs($member)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->postJson(route('rooms.pin', [
        'room' => 'TACT-PIN',
        'message' => $briefingMsg->id,
    ]));

    $failResponse->assertStatus(403);

    // Admin pins message
    $pinResponse = $this->actingAs($admin)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_is_admin_{$room->id}" => true,
    ])->postJson(route('rooms.pin', [
        'room' => 'TACT-PIN',
        'message' => $briefingMsg->id,
    ]));

    $pinResponse->assertStatus(200);
    $pinResponse->assertJsonPath('is_pinned', true);
    $pinResponse->assertJsonPath('pinned_message_id', $briefingMsg->id);

    expect($room->fresh()->pinned_message_id)->toBe($briefingMsg->id);

    // Messages index returns pinned briefing in stream
    $indexResponse = $this->actingAs($member)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => 'TACT-PIN']));

    $indexResponse->assertStatus(200);
    $indexResponse->assertJsonPath('pinned_message.id', $briefingMsg->id);
    $indexResponse->assertJsonPath('pinned_message.sender_name', 'Commander');
    $indexResponse->assertJsonPath('pinned_message.content', 'Rules of engagement: Strict stealth protocol in effect.');

    // Unpin toggle
    $unpinResponse = $this->actingAs($admin)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_is_admin_{$room->id}" => true,
    ])->postJson(route('rooms.pin', [
        'room' => 'TACT-PIN',
        'message' => $briefingMsg->id,
    ]));

    $unpinResponse->assertStatus(200);
    $unpinResponse->assertJsonPath('is_pinned', false);
    expect($room->fresh()->pinned_message_id)->toBeNull();
});
