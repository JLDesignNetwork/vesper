<?php

use App\Models\ChannelInvitation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

test('admin is routed to admin dashboard on login', function () {
    $admin = User::create([
        'name' => 'AdminCommander',
        'email' => 'admin@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $response = $this->post(route('login.post'), [
        'login' => 'admin@vesper.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('member is routed to operative channels hub on login', function () {
    // Create admin first so member is not the first user
    User::create([
        'name' => 'SystemAdmin',
        'email' => 'admin@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $member = User::create([
        'name' => 'AgentShadow',
        'email' => 'shadow@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $response = $this->post(route('login.post'), [
        'login' => 'shadow@vesper.test',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('channels.index'));
    $this->assertAuthenticatedAs($member);
});

test('member cannot access admin dashboard and is redirected to channels hub', function () {
    $member = User::create([
        'name' => 'OperativeRaven',
        'email' => 'raven@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $response = $this->actingAs($member)->get(route('admin.dashboard'));

    $response->assertRedirect(route('channels.index'));
});

test('channels hub enforces strict zero discovery and displays only enrolled channels', function () {
    $member = User::create([
        'name' => 'OperativeEcho',
        'email' => 'echo@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $enrolledRoom = Room::create([
        'code' => 'ALPHA-ENROLLED',
        'title' => 'Project Chimera',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'status' => 'active',
    ]);
    $enrolledRoom->addMember($member, 'member');

    $otherRoom = Room::create([
        'code' => 'OMEGA-SECRET',
        'title' => 'Blackwire Classified',
        'pin' => '999999',
        'passcode_hash' => Hash::make('999999'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($member)->get(route('channels.index'));

    $response->assertStatus(200);
    $response->assertSee('ALPHA-ENROLLED');
    $response->assertSee('Project Chimera');
    $response->assertDontSee('OMEGA-SECRET');
    $response->assertDontSee('Blackwire Classified');
});

test('enrolled member with active 2fa enters channel pinlessly', function () {
    $member = User::create([
        'name' => 'OperativePhoenix',
        'email' => 'phoenix@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);

    $room = Room::create([
        'code' => 'STREAM-PHX',
        'title' => 'Phoenix Outpost',
        'pin' => '777777',
        'passcode_hash' => Hash::make('777777'),
        'status' => 'active',
    ]);
    $room->addMember($member, 'member');

    expect($member->canUsePinlessEntry())->toBeTrue();

    $response = $this->actingAs($member)->post(route('channels.enter', $room->id));

    $response->assertRedirect(route('rooms.show', ['room' => $room->code]));

    // Following redirect should have clearance already granted in session
    $roomPage = $this->actingAs($member)->get(route('rooms.show', ['room' => $room->code]));
    $roomPage->assertStatus(200);
    $roomPage->assertViewIs('room');
});

test('enrolled member without 2fa requires pin entry', function () {
    $member = User::create([
        'name' => 'OperativeNova',
        'email' => 'nova@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
        // No 2FA or biometrics
    ]);

    $room = Room::create([
        'code' => 'SEC-NOVA',
        'title' => 'Nova Bunker',
        'pin' => '555555',
        'passcode_hash' => Hash::make('555555'),
        'status' => 'active',
    ]);
    $room->addMember($member, 'member');

    expect($member->canUsePinlessEntry())->toBeFalse();

    $response = $this->actingAs($member)->post(route('channels.enter', $room->id));

    // Redirects to rooms.show, but without pinless clearance, show() renders channel_entry PIN prompt
    $response->assertRedirect(route('rooms.show', ['room' => $room->code]));

    $roomPage = $this->actingAs($member)->get(route('rooms.show', ['room' => $room->code]));
    $roomPage->assertStatus(200);
    $roomPage->assertViewIs('channel_entry');
});

test('operative can redeem alphanumeric invitation code to join a channel', function () {
    $admin = User::create([
        'name' => 'AdminGhost',
        'email' => 'ghost@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $member = User::create([
        'name' => 'OperativeRecruit',
        'email' => 'recruit@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'NEXUS-MAIN',
        'title' => 'Nexus Ops',
        'pin' => '444444',
        'passcode_hash' => Hash::make('444444'),
        'status' => 'active',
    ]);

    $invitation = ChannelInvitation::createForRoom($room, $admin, maxUses: 5, expiresAt: now()->addDays(3));

    expect($room->isMember($member))->toBeFalse();

    $response = $this->actingAs($member)->post(route('channels.redeem'), [
        'code' => $invitation->code,
    ]);

    $response->assertRedirect(route('channels.index'));
    $response->assertSessionHas('status');

    expect($room->isMember($member))->toBeTrue();
    expect($invitation->fresh()->uses_count)->toBe(1);
});

test('operative can accept invite via invite link token', function () {
    $admin = User::create([
        'name' => 'AdminLinker',
        'email' => 'linker@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $member = User::create([
        'name' => 'OperativeTango',
        'email' => 'tango@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'TARGET-LINK',
        'title' => 'Tactical Grid',
        'pin' => '333333',
        'passcode_hash' => Hash::make('333333'),
        'status' => 'active',
    ]);

    $invitation = ChannelInvitation::createForRoom($room, $admin);

    // View invite link
    $viewResponse = $this->get(route('invites.show', ['token' => $invitation->token]));
    $viewResponse->assertStatus(200);
    $viewResponse->assertSee('TARGET-LINK');

    // Claim as authenticated member
    $acceptResponse = $this->actingAs($member)->post(route('invites.accept', ['token' => $invitation->token]));
    $acceptResponse->assertRedirect(route('channels.index'));

    expect($room->isMember($member))->toBeTrue();
});
