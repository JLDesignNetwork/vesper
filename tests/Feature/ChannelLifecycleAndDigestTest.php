<?php

use App\Enums\Rank;
use App\Mail\AdminOperationsDigestNotification;
use App\Mail\ChannelBurnWarningNotification;
use App\Mail\ChannelInvitationNotification;
use App\Mail\TwoFactorStatusNotification;
use App\Models\Room;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

test('vesper:channel-burn-check incinerates expired channels and purges media', function () {
    $admin = User::forceCreate([
        'name' => 'BurnAdmin',
        'email' => 'burnadmin@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
    ]);

    $expiredRoom = Room::create([
        'code' => 'EXPIRED-01',
        'pin' => '1234',
        'passcode_hash' => Hash::make('1234'),
        'status' => 'active',
        'expires_at' => now()->subMinutes(10),
        'created_by_user_id' => $admin->id,
    ]);

    $activeRoom = Room::create([
        'code' => 'ACTIVE-01',
        'pin' => '1234',
        'passcode_hash' => Hash::make('1234'),
        'status' => 'active',
        'expires_at' => now()->addHours(24),
        'created_by_user_id' => $admin->id,
    ]);

    $this->artisan('vesper:channel-burn-check')
        ->assertSuccessful();

    expect($expiredRoom->fresh()->status)->toBe('destroyed')
        ->and($activeRoom->fresh()->status)->toBe('active');
});

test('vesper:channel-burn-check dispatches burn warnings for channels expiring soon', function () {
    $member = User::forceCreate([
        'name' => 'WarnMember',
        'email' => 'warnmember@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'email_notifications' => true,
    ]);

    $imminentRoom = Room::create([
        'code' => 'BURN-SOON',
        'pin' => '1234',
        'passcode_hash' => Hash::make('1234'),
        'status' => 'active',
        'expires_at' => now()->addMinutes(45),
        'notify_admin' => false,
    ]);

    $imminentRoom->addMember($member);

    $this->artisan('vesper:channel-burn-check')
        ->assertSuccessful();

    Mail::assertQueued(ChannelBurnWarningNotification::class, function ($mail) use ($member, $imminentRoom) {
        return $mail->recipient->id === $member->id && $mail->room->id === $imminentRoom->id;
    });
});

test('vesper:send-operations-digest dispatches intelligence digest to administrators', function () {
    $admin = User::forceCreate([
        'name' => 'ExecutiveAdmin',
        'email' => 'exec@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
    ]);

    $this->artisan('vesper:send-operations-digest')
        ->assertSuccessful();

    Mail::assertQueued(AdminOperationsDigestNotification::class, function ($mail) use ($admin) {
        return $mail->admin->id === $admin->id;
    });
});

test('direct admin channel invitation dispatches ChannelInvitationNotification and redirects to admin.channels.index', function () {
    $admin = User::forceCreate([
        'name' => 'InvitingAdmin',
        'email' => 'invitingadmin@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::AdminOne,
    ]);

    $targetMember = User::forceCreate([
        'name' => 'TargetOperative',
        'email' => 'target@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
    ]);

    $room = Room::create([
        'code' => 'INVITE-TEST',
        'pin' => '1234',
        'passcode_hash' => Hash::make('1234'),
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.channels.invite', $room->id), [
        'user_id' => $targetMember->id,
    ]);

    $response->assertRedirect(route('admin.channels.index'));

    Mail::assertQueued(ChannelInvitationNotification::class, function ($mail) use ($targetMember, $room) {
        return $mail->recipient->id === $targetMember->id && $mail->room->id === $room->id;
    });
});

test('2FA confirmation and disable dispatch TwoFactorStatusNotification', function () {
    $user = User::forceCreate([
        'name' => 'TwoFactorAgent',
        'email' => 'twofactor@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
    ]);

    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();
    $validCode = $twoFactorService->calculateTotp($secret);
    session(['2fa_setup_secret' => $secret]);

    $response = $this->actingAs($user)->postJson(route('2fa.confirm'), [
        'code' => $validCode,
    ]);

    $response->assertStatus(200);

    Mail::assertQueued(TwoFactorStatusNotification::class, function ($mail) use ($user) {
        return $mail->user->id === $user->id && $mail->actionType === 'enabled';
    });

    // Now disable 2FA
    $disableResponse = $this->actingAs($user)->postJson(route('2fa.disable'), [
        'password' => 'password123',
    ]);

    $disableResponse->assertStatus(200);

    Mail::assertQueued(TwoFactorStatusNotification::class, function ($mail) use ($user) {
        return $mail->user->id === $user->id && $mail->actionType === 'disabled';
    });
});
