<?php

use App\Mail\NewMessageNotification;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('a user with clearance can transmit a text message', function () {
    $room = Room::create([
        'code' => 'CHAT-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Ghost',
    ])->postJson(route('messages.store', ['room' => 'CHAT-01']), [
        'content' => 'Rendezvous at target coordinates.',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message.content', 'Rendezvous at target coordinates.');
    $response->assertJsonPath('message.sender_name', 'Ghost');

    $this->assertDatabaseHas('messages', [
        'room_id' => $room->id,
        'sender_name' => 'Ghost',
    ]);
    $storedMsg = Message::where('room_id', $room->id)->first();
    expect($storedMsg->content)->toBe('Rendezvous at target coordinates.');
});

test('a user can upload an encrypted image attachment', function () {
    Storage::fake('local');

    $room = Room::create([
        'code' => 'IMG-SAFE',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $file = UploadedFile::fake()->image('surveillance.jpg', 600, 400);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Analyst',
    ])->postJson(route('messages.store', ['room' => 'IMG-SAFE']), [
        'content' => 'High-res reconnaissance photo',
        'attachment' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message.attachment_type', 'image');

    $message = Message::where('room_id', $room->id)->first();
    expect($message)->not->toBeNull();
    expect($message->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($message->attachment_path);
});

test('a user can upload a video attachment', function () {
    Storage::fake('local');

    $room = Room::create([
        'code' => 'VID-SAFE',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $file = UploadedFile::fake()->create('briefing.mp4', 1024, 'video/mp4');

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Director',
    ])->postJson(route('messages.store', ['room' => 'VID-SAFE']), [
        'content' => 'Tactical video brief',
        'attachment' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message.attachment_type', 'video');

    $message = Message::where('room_id', $room->id)->first();
    expect($message)->not->toBeNull();
    expect($message->attachment_type)->toBe('video');
    Storage::disk('local')->assertExists($message->attachment_path);
});

test('empty messages without attachments are rejected', function () {
    $room = Room::create([
        'code' => 'FAIL-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->postJson(route('messages.store', ['room' => 'FAIL-01']), [
        'content' => '',
    ]);

    $response->assertStatus(422);
});

test('live polling returns new messages since after_id', function () {
    $room = Room::create([
        'code' => 'POLL-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $m1 = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Agent 1',
        'sender_session_id' => 'sess_1',
        'content' => 'First transmission',
    ]);

    $m2 = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Agent 2',
        'sender_session_id' => 'sess_2',
        'content' => 'Second transmission',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', [
        'room' => 'POLL-01',
        'after_id' => $m1->id,
    ]));

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'messages');
    $response->assertJsonPath('messages.0.id', $m2->id);
    $response->assertJsonPath('messages.0.content', 'Second transmission');
});

test('message transmission succeeds and dispatches notifications without throwing errors', function () {
    $admin = User::create([
        'name' => 'NotifyAdmin',
        'email' => 'admin_notify@example.com',
        'password' => Hash::make('secret'),
        'role' => 'admin',
    ]);

    $member = User::create([
        'name' => 'NotifyMember',
        'email' => 'member_notify@example.com',
        'password' => Hash::make('secret'),
        'role' => 'member',
        'email_notifications' => true,
    ]);

    $room = Room::create([
        'code' => 'NOTIFY-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'notify_admin' => true,
        'created_by_user_id' => $admin->id,
    ]);

    // Member has previously posted in the room
    Message::create([
        'room_id' => $room->id,
        'user_id' => $member->id,
        'sender_name' => $member->name,
        'sender_session_id' => 'sess_member',
        'content' => 'Initial message',
    ]);

    $sender = User::create([
        'name' => 'SenderUser',
        'email' => 'sender@example.com',
        'password' => Hash::make('secret'),
        'role' => 'member',
    ]);

    Mail::fake();

    $response = $this->actingAs($sender)
        ->withSession([
            "room_clearance_{$room->id}" => true,
        ])
        ->postJson(route('messages.store', ['room' => 'NOTIFY-01']), [
            'content' => 'Transmission with notification broadcast.',
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message.content', 'Transmission with notification broadcast.');

    Mail::assertQueued(NewMessageNotification::class);
});

test('a user can send message with independent text and media ttl timers', function () {
    Storage::fake('local');

    $room = Room::create([
        'code' => 'DUAL-TTL-1',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $file = UploadedFile::fake()->image('intel.png', 200, 200);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Agent47',
    ])->postJson(route('messages.store', ['room' => 'DUAL-TTL-1']), [
        'content' => 'Classified blueprint details.',
        'attachment' => $file,
        'ttl_seconds' => 30,
        'media_ttl_seconds' => 10,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message.ttl_seconds', 30);
    $response->assertJsonPath('message.media_ttl_seconds', 10);
    $response->assertJsonPath('message.text_burned', false);
    $response->assertJsonPath('message.media_burned', false);

    $message = Message::where('room_id', $room->id)->first();
    expect($message->ttl_seconds)->toBe(30);
    expect($message->media_ttl_seconds)->toBe(10);
});

test('media burns independently while text remains readable when media ttl expires', function () {
    $watcher = User::factory()->create(['name' => 'MediaWatcher']);

    $room = Room::create([
        'code' => 'BURN-MEDIA',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Sender',
        'sender_session_id' => 'sender_session_xyz',
        'content' => 'Mission text persists.',
        'attachment_path' => 'attachments/test_image.jpg',
        'attachment_name' => 'intel.jpg',
        'attachment_type' => 'image',
        'attachment_mime' => 'image/jpeg',
        'attachment_size' => 1024,
        'ttl_seconds' => 3600, // 1 hour text
        'media_ttl_seconds' => 10, // 10s media
    ]);

    // First view initializes viewer's countdowns
    $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Viewer',
    ])->getJson(route('messages.index', ['room' => 'BURN-MEDIA']));

    // Fast-forward past media TTL but before text TTL
    $this->travel(15)->seconds();

    $response = $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Viewer',
    ])->getJson(route('messages.index', ['room' => 'BURN-MEDIA']));

    $response->assertStatus(200);
    $messages = $response->json('messages');
    expect(count($messages))->toBe(1);
    expect($messages[0]['content'])->toBe('Mission text persists.');
    expect($messages[0]['text_burned'])->toBeFalse();
    expect($messages[0]['media_burned'])->toBeTrue();
    expect($messages[0]['attachment_url'])->toBeNull();
});

test('text burns independently while media remains accessible when text ttl expires', function () {
    $watcher = User::factory()->create(['name' => 'TextWatcher']);

    $room = Room::create([
        'code' => 'BURN-TEXT',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Sender',
        'sender_session_id' => 'sender_session_xyz',
        'content' => 'Ephemeral text note.',
        'attachment_path' => 'attachments/test_intel.jpg',
        'attachment_name' => 'intel.jpg',
        'attachment_type' => 'image',
        'attachment_mime' => 'image/jpeg',
        'attachment_size' => 1024,
        'ttl_seconds' => 10, // 10s text
        'media_ttl_seconds' => 3600, // 1 hour media
    ]);

    // First view initializes countdown
    $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Viewer',
    ])->getJson(route('messages.index', ['room' => 'BURN-TEXT']));

    // Fast-forward past text TTL
    $this->travel(15)->seconds();

    $response = $this->actingAs($watcher)->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Viewer',
    ])->getJson(route('messages.index', ['room' => 'BURN-TEXT']));

    $response->assertStatus(200);
    $messages = $response->json('messages');
    expect(count($messages))->toBe(1);
    expect($messages[0]['content'])->toBeNull();
    expect($messages[0]['text_burned'])->toBeTrue();
    expect($messages[0]['media_burned'])->toBeFalse();
    expect($messages[0]['attachment_name'])->toBe('intel.jpg');
});
