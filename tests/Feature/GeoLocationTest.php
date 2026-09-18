<?php

use App\Models\AccessLog;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

test('geolocation service extracts real client IP from reverse proxy headers', function () {
    $service = new GeoLocationService;

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_CF_CONNECTING_IP' => '198.51.100.42',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    expect($service->getClientIp($request))->toBe('198.51.100.42');
});

test('geolocation service resolves location data and returns flag emoji', function () {
    $service = new GeoLocationService;

    $geo = $service->locate('127.0.0.1');

    expect($geo)->toHaveKeys(['ip', 'country', 'city', 'latitude', 'longitude', 'flag']);
    expect($geo['flag'])->toBe('🛡️');

    $usFlag = $service->countryCodeToFlag('US');
    expect($usFlag)->toBe('🇺🇸');
});

test('radar endpoint returns active operative tracking details', function () {
    $room = Room::create([
        'code' => 'RADAR-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    AccessLog::create([
        'room_id' => $room->id,
        'session_id' => 'agent_sess_1',
        'alias' => 'Valkyrie',
        'ip_address' => '203.0.113.19',
        'city' => 'Zurich',
        'country' => 'Switzerland',
        'country_code' => 'CH',
        'latitude' => 47.3769,
        'longitude' => 8.5417,
        'isp' => 'SwissCom',
        'last_seen_at' => now(),
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('intel.radar', ['room' => 'RADAR-01']));

    $response->assertStatus(200);
    $response->assertJsonPath('total_active', 1);
    $response->assertJsonPath('operatives.0.alias', 'Valkyrie');
    $response->assertJsonPath('operatives.0.city', 'Zurich');
    $response->assertJsonPath('operatives.0.ip_address', '203.0.113.19');
    $response->assertJsonPath('operatives.0.flag', '🇨🇭');
});

test('a user can sync their high precision browser GPS coordinates', function () {
    $room = Room::create([
        'code' => 'GPS-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->postJson(route('intel.gps', ['room' => 'GPS-01']), [
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('latitude', 40.7128);
    $response->assertJsonPath('longitude', -74.0060);
});

test('gps sync persists coordinates to authenticated user model and access logs', function () {
    $user = User::create([
        'name' => 'AgentGPS',
        'email' => 'agentgps@vesper.test',
        'password' => Hash::make('password'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'GPS-USER-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $sessionId = 'test_session_gps_1';

    $response = $this->actingAs($user)
        ->withSession([
            "room_clearance_{$room->id}" => true,
        ])
        ->postJson(route('intel.gps', ['room' => 'GPS-USER-01']), [
            'latitude' => 45.4642,
            'longitude' => 9.1900,
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $user->refresh();
    expect($user->latitude)->toBe(45.4642);
    expect($user->longitude)->toBe(9.19);
    expect($user->location_synced_at)->not->toBeNull();
    expect($user->hasGps())->toBeTrue();

    // Verify AccessLog was tied to user_id and has GPS
    $log = AccessLog::where('user_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->latitude)->toBe(45.4642);
    expect($log->longitude)->toBe(9.19);
});

test('admin can synchronize gps via admin.gps endpoint', function () {
    $admin = User::create([
        'name' => 'AdminCommander',
        'email' => 'admin_gps@vesper.test',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.gps'), [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $admin->refresh();
    expect($admin->latitude)->toBe(51.5074);
    expect($admin->longitude)->toBe(-0.1278);
    expect($admin->location_synced_at)->not->toBeNull();

    // Verify admin dashboard mapMarkers includes this user
    $dashResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
    $dashResponse->assertStatus(200);
    $dashResponse->assertSee('AdminCommander');
    $dashResponse->assertSee('51.5074');
});

test('user with saved gps retains coordinates on subsequent room visits instead of ip fallback', function () {
    $user = User::create([
        'name' => 'PersistentGPS',
        'email' => 'persistent@vesper.test',
        'password' => Hash::make('password'),
        'role' => 'member',
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'city' => 'Paris',
        'country' => 'France',
        'country_code' => 'FR',
    ]);

    $room = Room::create([
        'code' => 'PARIS-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)
        ->withSession([
            "room_clearance_{$room->id}" => true,
        ])
        ->get(route('rooms.show', ['room' => 'PARIS-01']));

    $response->assertStatus(200);

    // Verify AccessLog stored Paris coordinates, not localhost fallback (37.7749)
    $log = AccessLog::where('user_id', $user->id)->first();
    expect($log)->not->toBeNull();
    expect($log->latitude)->toBe(48.8566);
    expect($log->longitude)->toBe(2.3522);
    expect($log->city)->toBe('Paris');
    expect($log->country)->toBe('France');
});

test('user can synchronize gps via profile.gps endpoint', function () {
    $user = User::create([
        'name' => 'ProfileGPSUser',
        'email' => 'profilegps@vesper.test',
        'password' => Hash::make('password'),
        'role' => 'member',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('profile.gps'), [
            'latitude' => 35.6762,
            'longitude' => 139.6503,
        ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);

    $user->refresh();
    expect($user->latitude)->toBe(35.6762);
    expect($user->longitude)->toBe(139.6503);
});
