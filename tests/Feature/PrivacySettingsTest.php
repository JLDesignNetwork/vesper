<?php

use App\Enums\Rank;
use App\Models\AccessLog;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('member can update their privacy settings', function () {
    $user = User::forceCreate([
        'name' => 'AgentShadow',
        'email' => 'shadow@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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
    $privateUser = User::forceCreate([
        'name' => 'AgentGhost',
        'email' => 'ghost@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'birthday' => '1992-04-10',
        'gender' => 'Male',
        'location' => 'Zurich, Switzerland',
        'bio' => 'Stealth operative.',
        'hide_age' => true,
        'hide_birthday' => true,
        'hide_location' => true,
        'hide_bio' => true,
    ]);

    $otherUser = User::forceCreate([
        'name' => 'AgentObserver',
        'email' => 'observer@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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
    $room->addMember($privateUser); // The card only looks up people connected to the channel.

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
    $privateUser = User::forceCreate([
        'name' => 'AgentStealth',
        'email' => 'stealth@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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
    $room->addMember($privateUser); // The card only looks up people connected to the channel.

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

    $admin = User::forceCreate([
        'name' => 'CommanderAdmin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
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
    $author = User::forceCreate([
        'name' => 'AgentCipher',
        'email' => 'cipher@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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
    $peer = User::forceCreate([
        'name' => 'AgentPeer',
        'email' => 'peer@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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
    $admin = User::forceCreate([
        'name' => 'GeneralAdmin',
        'email' => 'general@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
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

test('hiding age while allowing birthday conceals the birth year and shows only month and day to members', function () {
    $user = User::forceCreate([
        'name' => 'AgentChronos',
        'email' => 'chronos@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'birthday' => '1995-06-20',
        'gender' => 'Non-binary',
        'location' => 'Geneva, Switzerland',
        'bio' => 'Temporal analyst.',
        'hide_age' => true,
        'hide_birthday' => false,
    ]);

    $peer = User::forceCreate([
        'name' => 'AgentWatcher',
        'email' => 'watcher@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
    ]);

    $admin = User::forceCreate([
        'name' => 'ChronosAdmin',
        'email' => 'chronos-admin@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
    ]);

    $room = Room::create([
        'code' => 'CHRON-99',
        'title' => 'Temporal Channel',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);
    $room->addMember($user); // The card only looks up people connected to the channel.

    Message::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'sender_name' => $user->name,
        'sender_session_id' => 'session-chronos',
        'content' => 'Chronos online.',
    ]);

    // 1. Regular peer member queries memberProfile
    $this->actingAs($peer);
    $cardResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentChronos']));

    $cardResponse->assertStatus(200);
    $cardData = $cardResponse->json();
    expect($cardData['age'])->toBeNull();
    // Must show month and day only, NOT the year 1995
    expect($cardData['birthday'])->toBe('June 20');
    expect($cardData['privacy']['age_hidden'])->toBeTrue();
    expect($cardData['privacy']['birthday_hidden'])->toBeFalse();

    // 2. Regular peer member fetches messages
    $msgResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => $room->code]));

    $msgResponse->assertStatus(200);
    $peerMsg = $msgResponse->json('messages.0');
    expect($peerMsg['sender_age'])->toBeNull();
    expect($peerMsg['sender_birthday'])->toBe('June 20');

    // 3. User themselves viewing their own profile sees full year
    $this->actingAs($user);
    $selfCardResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentChronos']));

    $selfCardData = $selfCardResponse->json();
    expect($selfCardData['age'])->not->toBeNull();
    expect($selfCardData['birthday'])->toBe('1995-06-20');

    // 4. Admin viewing profile sees full year
    $this->actingAs($admin);
    $adminCardResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentChronos']));

    $adminCardData = $adminCardResponse->json();
    expect($adminCardData['age'])->not->toBeNull();
    expect($adminCardData['birthday'])->toBe('1995-06-20');

    // 5. Admin fetching messages sees full year
    $adminMsgResponse = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('messages.index', ['room' => $room->code]));

    $adminMsg = $adminMsgResponse->json('messages.0');
    expect($adminMsg['sender_age'])->not->toBeNull();
    expect($adminMsg['sender_birthday'])->toBe('1995-06-20');
});

test('user hiding their location does not show up on the map inside any chat channel while admin section retains full visibility', function () {
    $hiddenUser = User::forceCreate([
        'name' => 'AgentMirage',
        'email' => 'mirage@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'city' => 'Zurich',
        'country' => 'Switzerland',
        'country_code' => 'CH',
        'latitude' => 47.3769,
        'longitude' => 8.5417,
        'location' => 'Zurich, Switzerland',
        'hide_location' => true,
        'location_synced_at' => now(),
    ]);

    $visibleUser = User::forceCreate([
        'name' => 'AgentBeacon',
        'email' => 'beacon@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'city' => 'Geneva',
        'country' => 'Switzerland',
        'country_code' => 'CH',
        'latitude' => 46.2044,
        'longitude' => 6.1432,
        'location' => 'Geneva, Switzerland',
        'hide_location' => false,
        'location_synced_at' => now(),
    ]);

    $observerPeer = User::forceCreate([
        'name' => 'AgentObserver2',
        'email' => 'observer2@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
    ]);

    $admin = User::forceCreate([
        'name' => 'CommandAdmin',
        'email' => 'command@example.com',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
    ]);

    $room = Room::create([
        'code' => 'RADAR-PRIV',
        'title' => 'Tactical Channel',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    // Create access logs for both users in the room
    AccessLog::create([
        'room_id' => $room->id,
        'user_id' => $hiddenUser->id,
        'session_id' => 'session-mirage',
        'alias' => $hiddenUser->name,
        'ip_address' => '198.51.100.77',
        'city' => 'Zurich',
        'region' => 'Zurich',
        'country' => 'Switzerland',
        'country_code' => 'CH',
        'latitude' => 47.3769,
        'longitude' => 8.5417,
        'last_seen_at' => now(),
    ]);

    AccessLog::create([
        'room_id' => $room->id,
        'user_id' => $visibleUser->id,
        'session_id' => 'session-beacon',
        'alias' => $visibleUser->name,
        'ip_address' => '198.51.100.88',
        'city' => 'Geneva',
        'region' => 'Geneva',
        'country' => 'Switzerland',
        'country_code' => 'CH',
        'latitude' => 46.2044,
        'longitude' => 6.1432,
        'last_seen_at' => now(),
    ]);

    // 1. Peer member cannot open the channel radar at all (owner decision: Admin I only)
    $this->actingAs($observerPeer);
    $peerRadar = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('intel.radar', ['room' => $room->code]));

    $peerRadar->assertForbidden();
    $peerRadar->assertDontSee('AgentMirage');
    $peerRadar->assertDontSee('AgentBeacon');
    $peerRadar->assertDontSee('198.51.100.77');
    $peerRadar->assertDontSee('198.51.100.88');

    // 2. Admin I radar shows both members, including the one who hid their location
    $this->actingAs($admin);
    $adminRadar = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('intel.radar', ['room' => $room->code]));

    $adminRadar->assertStatus(200);
    $operatives = collect($adminRadar->json('operatives'));
    $mirageOp = $operatives->firstWhere('alias', 'AgentMirage');
    $beaconOp = $operatives->firstWhere('alias', 'AgentBeacon');
    expect($mirageOp['city'])->toBe('Zurich');
    expect($mirageOp['ip_address'])->toBe('198.51.100.77');
    expect($mirageOp['location_hidden'])->toBeTrue();
    expect($beaconOp['latitude'])->toBe(46.2044);
    expect($beaconOp['city'])->toBe('Geneva');

    // 3. Admin section dashboard STILL shows AgentMirage on the Global Traffic Map and in user list
    $this->actingAs($admin);
    $dashResponse = $this->get(route('admin.dashboard'));
    $dashResponse->assertStatus(200);
    $dashResponse->assertSee('AgentMirage');
    $dashResponse->assertSee('47.3769'); // Latitude present in admin map markers
    $dashResponse->assertSee('8.5417');  // Longitude present in admin map markers
    $dashResponse->assertSee('Zurich');  // City present in admin dossier and table
});
