<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('portal page loads with discreet authentication portal', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Vesper');
});

test('admin can create a private room from the dashboard', function () {
    $admin = User::create([
        'name' => 'Commander',
        'email' => 'commander@sundaycity.local',
        'password' => Hash::make('pass'),
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.channels.store'), [
        'code' => 'TEST-SAFE-01',
        'title' => 'Alpha Safehouse',
        'passcode' => 'classified-pass',
        'expiration' => '24h',
        'burn_after_reading' => 0,
    ]);

    $room = Room::where('code', 'TEST-SAFE-01')->first();
    expect($room)->not->toBeNull();
    expect(Hash::check('classified-pass', $room->passcode_hash))->toBeTrue();

    $response->assertRedirect(route('admin.channels.index'));
});

test('an unauthorized user visiting a room is presented with the passkey prompt', function () {
    $room = Room::create([
        'code' => 'SECRET-99',
        'passcode_hash' => Hash::make('mypassword'),
        'status' => 'active',
    ]);

    $response = $this->get(route('rooms.show', ['room' => 'SECRET-99']));

    $response->assertStatus(200);
    $response->assertSee('SECRET-99');
    $response->assertSee('Passcode');
});

test('verifying with the correct passcode grants access and sets clearance', function () {
    $room = Room::create([
        'code' => 'COVERT-42',
        'passcode_hash' => Hash::make('vault-key-77'),
        'status' => 'active',
    ]);

    $response = $this->post(route('rooms.verify', ['room' => 'COVERT-42']), [
        'passcode' => 'vault-key-77',
        'alias' => 'Spectre',
    ]);

    $response->assertRedirect(route('rooms.show', ['room' => 'COVERT-42']));
    $this->assertTrue(session()->has("room_clearance_{$room->id}"));
    expect(session("room_alias_{$room->id}"))->toBe('Spectre');
});

test('verifying with an incorrect passcode is rejected', function () {
    $room = Room::create([
        'code' => 'LOCKED-01',
        'passcode_hash' => Hash::make('correct-key'),
        'status' => 'active',
    ]);

    $response = $this->post(route('rooms.verify', ['room' => 'LOCKED-01']), [
        'passcode' => 'wrong-key',
    ]);

    $response->assertSessionHasErrors('passcode');
    $this->assertFalse(session()->has("room_clearance_{$room->id}"));
});

test('emergency nuke action purges the room, all messages, and storage directory', function () {
    Storage::fake('public');

    $room = Room::create([
        'code' => 'NUKE-TARGET',
        'passcode_hash' => Hash::make('password'),
        'status' => 'active',
    ]);

    // Create a dummy message with attachment
    Storage::disk('public')->put("attachments/{$room->id}/classified.jpg", 'dummy-content');

    Message::create([
        'room_id' => $room->id,
        'sender_name' => 'Agent',
        'sender_session_id' => 'dummy-session',
        'content' => 'Top secret communication',
        'attachment_path' => "attachments/{$room->id}/classified.jpg",
    ]);

    // Execute nuke with active session clearance
    $response = $this->withSession(["room_clearance_{$room->id}" => true])
        ->post(route('rooms.nuke', ['room' => 'NUKE-TARGET']));

    $response->assertRedirect(route('portal'));
    expect(Room::where('code', 'NUKE-TARGET')->first())->toBeNull();
    expect(Message::where('room_id', $room->id)->count())->toBe(0);
    Storage::disk('public')->assertMissing("attachments/{$room->id}/classified.jpg");
});

test('authenticated admin user can directly access room and view interface without error', function () {
    $admin = User::create([
        'name' => 'Commander',
        'email' => 'commander@sundaycity.local',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $room = Room::create([
        'code' => 'ADMIN-DIRECT',
        'passcode_hash' => Hash::make('password'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->get(route('rooms.show', ['room' => 'ADMIN-DIRECT']));
    $response->assertStatus(200);
    $response->assertSee('Vesper');
    $response->assertSee('NETWORK MAP');
});

test('network map is only visible to admin role and completely hidden for regular members', function () {
    $admin = User::create([
        'name' => 'GeneralAdmin',
        'email' => 'general@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $member = User::create([
        'name' => 'GrantMember',
        'email' => 'grant@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'ROLE-MAP-01',
        'passcode_hash' => Hash::make('password'),
        'status' => 'active',
    ]);

    // Admin in room sees Network Map
    $adminResponse = $this->actingAs($admin)->get(route('rooms.show', ['room' => $room->code]));
    $adminResponse->assertStatus(200);
    $adminResponse->assertSee('NETWORK MAP');
    $adminResponse->assertSee('id="radar-drawer"', false);

    // Regular Member in room does NOT see Network Map
    $memberResponse = $this->actingAs($member)->withSession([
        "room_clearance_{$room->id}" => true,
    ])->get(route('rooms.show', ['room' => $room->code]));
    $memberResponse->assertStatus(200);
    $memberResponse->assertDontSee('NETWORK MAP');
    $memberResponse->assertDontSee('id="radar-drawer"', false);
});
