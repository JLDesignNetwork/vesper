<?php

use App\Models\AccessLog;
use App\Models\Room;
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
