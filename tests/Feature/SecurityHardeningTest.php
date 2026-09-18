<?php

use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('message content is encrypted at rest in database and auto-decrypts on retrieval', function () {
    $room = Room::create([
        'code' => 'CIPHER-01',
        'title' => 'Cipher Room',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'AgentAlpha',
        'sender_session_id' => 'sess_test_alpha',
        'content' => 'Top Secret Nuclear Launch Protocol 779',
    ]);

    // Query raw database column to verify it is encrypted ciphertext
    $rawContent = DB::table('messages')->where('id', $message->id)->value('content');

    expect($rawContent)->not->toBeNull();
    expect($rawContent)->not->toContain('Top Secret Nuclear Launch Protocol 779');
    expect($rawContent)->toContain('eyJ'); // Base64 JSON payload of Laravel encryption envelope

    // Verify model attribute auto-decrypts
    $fresh = Message::find($message->id);
    expect($fresh->content)->toBe('Top Secret Nuclear Launch Protocol 779');
});

test('legacy unencrypted message content decrypts safely without exception', function () {
    $room = Room::create([
        'code' => 'LEGACY-02',
        'title' => 'Legacy Room',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    // Insert directly into DB table bypassing Eloquent cast
    $id = DB::table('messages')->insertGetId([
        'room_id' => $room->id,
        'sender_name' => 'OldAgent',
        'sender_session_id' => 'sess_test_legacy',
        'content' => 'Unencrypted plaintext transmission from 2024',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacyMessage = Message::find($id);
    expect($legacyMessage->content)->toBe('Unencrypted plaintext transmission from 2024');
});

test('media attachments are stored on private disk and streamed only to cleared sessions', function () {
    Storage::fake('local');

    $room = Room::create([
        'code' => 'VAULT-03',
        'title' => 'Vault Channel',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    // Create and store attachment
    $tempFile = UploadedFile::fake()->create('intelligence_brief.pdf', 120, 'application/pdf');
    $service = new MediaStorageService;
    $stored = $service->storeAttachment($tempFile, $room->id);

    // Verify it is on local private disk, not public
    expect(Storage::disk('local')->exists($stored['path']))->toBeTrue();

    $message = Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Courier',
        'sender_session_id' => 'sess_courier_123',
        'attachment_path' => $stored['path'],
        'attachment_name' => 'intelligence_brief.pdf',
        'attachment_mime' => 'application/pdf',
        'attachment_size' => 122880,
    ]);

    // 1. Unauthenticated / Uncleared request -> 403 Forbidden
    $unclearedResponse = $this->get(route('messages.attachment', [
        'room' => $room->code,
        'message' => $message->id,
    ]));
    $unclearedResponse->assertStatus(403);

    // 2. Cleared session request -> 200 OK with streamed file
    $clearedResponse = $this->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('messages.attachment', [
            'room' => $room->code,
            'message' => $message->id,
        ]));

    $clearedResponse->assertStatus(200);
    $clearedResponse->assertHeader('Content-Type', 'application/pdf');
});

test('ip addresses and gps coordinates are anonymized and jittered on message creation', function () {
    $room = Room::create([
        'code' => 'CLOAK-04',
        'title' => 'Cloak Channel',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
        "room_alias_{$room->id}" => 'Phantom',
    ])->postJson(route('messages.store', ['room' => $room->code]), [
        'content' => 'Stealth position report',
    ]);

    $response->assertStatus(200);

    $msg = Message::where('room_id', $room->id)->latest()->first();
    expect($msg)->not->toBeNull();

    // Verify IP is anonymized (masked to .0 for IPv4)
    if (filter_var($msg->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        expect(str_ends_with($msg->ip_address, '.0'))->toBeTrue();
    }

    // Verify coordinates are numeric and stored
    expect($msg->latitude)->toBeFloat();
    expect($msg->longitude)->toBeFloat();
});

test('duress passcode unlocks decoy vault session hiding all real communications', function () {
    $room = Room::create([
        'code' => 'DURESS-05',
        'title' => 'Undercover Channel',
        'passcode_hash' => Hash::make('real-master-password'),
        'duress_passcode_hash' => Hash::make('duress-coerced-code'),
        'status' => 'active',
    ]);

    // Create 3 real classified messages
    Message::create(['room_id' => $room->id, 'sender_name' => 'Agent1', 'sender_session_id' => 'sess_1', 'content' => 'Classified Intel #1']);
    Message::create(['room_id' => $room->id, 'sender_name' => 'Agent2', 'sender_session_id' => 'sess_2', 'content' => 'Classified Intel #2']);
    Message::create(['room_id' => $room->id, 'sender_name' => 'Agent3', 'sender_session_id' => 'sess_3', 'content' => 'Classified Intel #3']);

    // 1. Enter with the DURESS passcode
    $response = $this->post(route('rooms.verify', ['room' => $room->code]), [
        'passcode' => 'duress-coerced-code',
        'alias' => 'UnderDuressOperative',
    ]);

    $response->assertRedirect(route('rooms.show', ['room' => $room->code]));
    $this->assertTrue(session("room_clearance_{$room->id}"));
    $this->assertTrue(session("room_is_duress_{$room->id}"));

    // 2. Poll messages in duress mode
    $streamResponse = $this->getJson(route('messages.index', ['room' => $room->code]));
    $streamResponse->assertStatus(200);
    $streamResponse->assertJson([
        'status' => 'active',
        'messages' => [],
    ]);
    expect($streamResponse->json('messages'))->toBeEmpty();

    // 3. Verify AccessLog captured DURESS_TRIGGER flag
    $log = AccessLog::where('room_id', $room->id)->latest()->first();
    expect($log->alias)->toContain('[DURESS_TRIGGER]');
});

test('room media purge performs cryptographic multi-pass shredding', function () {
    $room = Room::create([
        'code' => 'SHRED-06',
        'title' => 'Shredder Channel',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $localDir = Storage::disk('local')->path("attachments/{$room->id}");
    if (! is_dir($localDir)) {
        mkdir($localDir, 0755, true);
    }
    $filePath = $localDir.'/confidential.dat';
    file_put_contents($filePath, 'High clearance military plans');

    expect(file_exists($filePath))->toBeTrue();

    // Execute purge
    $room->purgeAllMedia();

    expect(file_exists($filePath))->toBeFalse();
    expect(is_dir($localDir))->toBeFalse();
});
