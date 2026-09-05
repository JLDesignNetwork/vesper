<?php

use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('member can update their privacy settings', function () {
    $user = User::create([
        'name' => 'AgentShadow',
        'email' => 'shadow@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'birthday' => '1995-06-20',
        'location' => 'Geneva, Switzerland',
        'bio' => 'Classified operative.',
    ]);

    $this->actingAs($user);

    $response = $this->post(route('profile.update'), [
        'name' => 'AgentShadow',
        'email' => 'shadow@example.com',
        'hide_age' => 1,
        'hide_birthday' => 1,
        'hide_location' => 1,
        'hide_bio' => 1,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $user->refresh();
    expect($user->hide_age)->toBeTrue();
    expect($user->hide_birthday)->toBeTrue();
    expect($user->hide_location)->toBeTrue();
    expect($user->hide_bio)->toBeTrue();
});

test('member card endpoint hides age, birthday, location, and bio from other regular members', function () {
    $privateUser = User::create([
        'name' => 'AgentGhost',
        'email' => 'ghost@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'birthday' => '1992-04-10',
        'gender' => 'Male',
        'location' => 'Zurich, Switzerland',
        'bio' => 'Stealth operative.',
        'hide_age' => true,
        'hide_birthday' => true,
        'hide_location' => true,
        'hide_bio' => true,
    ]);

    $otherUser = User::create([
        'name' => 'AgentObserver',
        'email' => 'observer@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'PRIV-1001',
        'title' => 'Privacy Room',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    // Regular member viewing private user
    $this->actingAs($otherUser);
    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentGhost']));

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['found'])->toBeTrue();
    expect($data['name'])->toBe('AgentGhost');
    expect($data['age'])->toBeNull();
    expect($data['birthday'])->toBeNull();
    expect($data['location'])->toBeNull();
    expect($data['bio'])->toBeNull();
    expect($data['gender'])->toBe('Male'); // non-hidden field remains visible
    expect($data['privacy']['age_hidden'])->toBeTrue();
    expect($data['privacy']['birthday_hidden'])->toBeTrue();
    expect($data['privacy']['location_hidden'])->toBeTrue();
    expect($data['privacy']['bio_hidden'])->toBeTrue();
});

test('admin center and admin viewer can see all information regardless of user privacy settings', function () {
    $privateUser = User::create([
        'name' => 'AgentStealth',
        'email' => 'stealth@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'birthday' => '1990-08-15',
        'gender' => 'Female',
        'location' => 'Vienna, Austria',
        'bio' => 'Signals intelligence specialist.',
        'hide_age' => true,
        'hide_birthday' => true,
        'hide_location' => true,
        'hide_bio' => true,
    ]);

    $room = Room::create([
        'code' => 'ADMIN-2002',
        'title' => 'Command Channel',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    // Create access log with IP for the user
    AccessLog::create([
        'room_id' => $room->id,
        'user_id' => $privateUser->id,
        'session_id' => 'test-stealth-session',
        'alias' => $privateUser->name,
        'ip_address' => '198.51.100.42',
        'country' => 'Austria',
        'city' => 'Vienna',
        'latitude' => 48.2082,
        'longitude' => 16.3738,
        'last_seen_at' => now(),
    ]);

    $admin = User::create([
        'name' => 'CommanderAdmin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    // 1. Admin viewing member card in chat -> sees EVERYTHING
    $this->actingAs($admin);
    $cardResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentStealth']));

    $cardResponse->assertStatus(200);
    $cardData = $cardResponse->json();
    expect($cardData['age'])->not->toBeNull();
    expect($cardData['birthday'])->toBe('1990-08-15');
    expect($cardData['location'])->toBe('Vienna, Austria');
    expect($cardData['bio'])->toBe('Signals intelligence specialist.');
    expect($cardData['is_admin'])->toBeTrue();

    // 2. Admin dashboard displays user's latest IP and unmasked profile
    $dashboardResponse = $this->get(route('admin.dashboard'));
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertSee('198.51.100.42');
    $dashboardResponse->assertSee('AgentStealth');
    $dashboardResponse->assertSee('Vienna, Austria');

    // 3. User model serialization includes latest_ip attribute
    expect($privateUser->latestIp())->toBe('198.51.100.42');
    expect($privateUser->toArray())->toHaveKey('latest_ip', '198.51.100.42');
});

test('messages stream redacts hidden fields for regular members but reveals them for admin', function () {
    $author = User::create([
        'name' => 'AgentCipher',
        'email' => 'cipher@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'birthday' => '1988-12-05',
        'location' => 'Berlin, Germany',
        'bio' => 'Cryptography lead.',
        'hide_age' => true,
        'hide_birthday' => true,
        'hide_location' => true,
        'hide_bio' => true,
    ]);

    $room = Room::create([
        'code' => 'CIPH-3003',
        'title' => 'Cipher Room',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    Message::create([
        'room_id' => $room->id,
        'user_id' => $author->id,
        'sender_name' => $author->name,
        'sender_session_id' => 'session-cipher-author',
        'content' => 'Secure transmission test.',
    ]);

    // Regular member reads stream
    $peer = User::create([
        'name' => 'AgentPeer',
        'email' => 'peer@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $this->actingAs($peer);
    $peerResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => $room->code]));

    $peerResponse->assertStatus(200);
    $peerMsg = $peerResponse->json('messages.0');
    expect($peerMsg['sender_age'])->toBeNull();
    expect($peerMsg['sender_birthday'])->toBeNull();
    expect($peerMsg['sender_location'])->toBeNull();
    expect($peerMsg['sender_bio'])->toBeNull();

    // Admin reads stream
    $admin = User::create([
        'name' => 'GeneralAdmin',
        'email' => 'general@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->actingAs($admin);
    $adminResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => $room->code]));

    $adminResponse->assertStatus(200);
    $adminMsg = $adminResponse->json('messages.0');
    expect($adminMsg['sender_age'])->not->toBeNull();
    expect($adminMsg['sender_birthday'])->toBe('1988-12-05');
    expect($adminMsg['sender_location'])->toBe('Berlin, Germany');
    expect($adminMsg['sender_bio'])->toBe('Cryptography lead.');
});
