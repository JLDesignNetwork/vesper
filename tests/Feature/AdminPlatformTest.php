<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

test('guest accessing admin dashboard is redirected to login', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});

test('first visitor can set up the primary admin account', function () {
    expect(User::count())->toBe(0);

    $response = $this->get(route('login'));
    $response->assertStatus(200);
    $response->assertSee('Admin Setup');

    $postResponse = $this->post(route('login.post'), [
        'name' => 'CommanderJeff',
        'email' => 'jeff@sundaycity.local',
        'password' => 'secret-admin-pass',
    ]);

    $postResponse->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated();

    $user = User::where('email', 'jeff@sundaycity.local')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('CommanderJeff');
});

test('admin can log in with either username or email', function () {
    $user = User::create([
        'name' => 'AgentAdmin',
        'email' => 'agent@sundaycity.local',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    // Test login with username
    $responseName = $this->post(route('login.post'), [
        'login' => 'AgentAdmin',
        'password' => 'password123',
    ]);
    $responseName->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);

    // Logout
    $this->post(route('logout'));
    $this->assertGuest();

    // Test login with email
    $responseEmail = $this->post(route('login.post'), [
        'login' => 'agent@sundaycity.local',
        'password' => 'password123',
    ]);
    $responseEmail->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('admin can create private channels from the dashboard', function () {
    $admin = User::create([
        'name' => 'Chief',
        'email' => 'chief@sundaycity.local',
        'password' => Hash::make('pass'),
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.channels.store'), [
        'title' => 'VIP Strategy Room',
        'code' => 'VIP-99',
        'passcode' => 'pass1234',
        'expiration' => '24h',
        'burn_after_reading' => 1,
    ]);

    $response->assertRedirect(route('admin.dashboard'));

    $room = Room::where('code', 'VIP-99')->first();
    expect($room)->not->toBeNull();
    expect($room->title)->toBe('VIP Strategy Room');
    expect($room->created_by_user_id)->toBe($admin->id);
    expect($room->burn_after_reading)->toBeTrue();
});

test('admin can toggle and purge channels from the dashboard', function () {
    $admin = User::create([
        'name' => 'Director',
        'email' => 'director@sundaycity.local',
        'password' => Hash::make('pass'),
        'role' => 'admin',
    ]);

    $room = Room::create([
        'code' => 'DISPOSABLE-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $admin->id,
    ]);

    // Toggle status to archived
    $toggleResponse = $this->actingAs($admin)->post(route('admin.channels.toggle', ['id' => $room->id]));
    $toggleResponse->assertRedirect(route('admin.dashboard'));
    expect($room->fresh()->status)->toBe('archived');

    // Purge channel
    $deleteResponse = $this->actingAs($admin)->delete(route('admin.channels.destroy', ['id' => $room->id]));
    $deleteResponse->assertRedirect(route('admin.dashboard'));
    expect(Room::where('code', 'DISPOSABLE-01')->first())->toBeNull();
});

test('supports French and Italian localizations and translations', function () {
    // Test French locale switch
    $resFr = $this->get(route('locale.switch', ['locale' => 'fr']));
    $resFr->assertStatus(302);
    expect(session('locale'))->toBe('fr');

    // Test Italian locale switch
    $resIt = $this->get(route('locale.switch', ['locale' => 'it']));
    $resIt->assertStatus(302);
    expect(session('locale'))->toBe('it');

    // Test TranslationService into French and Italian
    Http::fake([
        'https://clients5.google.com/*' => Http::response([
            ['Bonjour le monde', 'en'],
        ], 200),
        'https://api.mymemory.translated.net/*' => Http::response([
            'responseData' => [
                'translatedText' => 'Bonjour le monde',
            ],
        ], 200),
    ]);

    $service = app(\App\Services\TranslationService::class);
    $frResult = $service->translate('Hello world', 'fr');
    expect($frResult['success'])->toBeTrue();
    expect($frResult['translated_text'])->toBe('Bonjour le monde');
    expect($frResult['target_lang'])->toBe('fr');
});

test('admin can access all dedicated admin subpages', function () {
    $admin = User::create([
        'name' => 'GeneralVesper',
        'email' => 'general@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $this->actingAs($admin);

    // 1. Overview
    $resOverview = $this->get(route('admin.dashboard'));
    $resOverview->assertStatus(200);
    $resOverview->assertSee('Operations Overview');

    // 2. Channels Page
    $resChannels = $this->get(route('admin.channels.index'));
    $resChannels->assertStatus(200);
    $resChannels->assertSee('Encrypted Channels');

    // 3. Operatives Page
    $resOperatives = $this->get(route('admin.operatives.index'));
    $resOperatives->assertStatus(200);
    $resOperatives->assertSee('Registered Operatives');

    // 4. Global Intel Page
    $resIntel = $this->get(route('admin.intel.index'));
    $resIntel->assertStatus(200);
    $resIntel->assertSee('Global Intelligence');

    // 5. Transmission Logs Page
    $resLogs = $this->get(route('admin.logs.index'));
    $resLogs->assertStatus(200);
    $resLogs->assertSee('Transmission Logs');
});

test('non-admin member is blocked from dedicated admin subpages', function () {
    $member = User::create([
        'name' => 'FootSoldier',
        'email' => 'soldier@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $this->actingAs($member);

    $this->get(route('admin.channels.index'))->assertRedirect(route('channels.index'));
    $this->get(route('admin.operatives.index'))->assertRedirect(route('channels.index'));
    $this->get(route('admin.intel.index'))->assertRedirect(route('channels.index'));
    $this->get(route('admin.logs.index'))->assertRedirect(route('channels.index'));
});

