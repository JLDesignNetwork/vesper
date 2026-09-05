<?php

use App\Mail\EmergencyAccountRecovery;
use App\Mail\RecoveryEmailVerification;
use App\Mail\SecurityAlertNotification;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\OAuthService;
use App\Services\TwoFactorService;
use App\Services\WebAuthnService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/* -------------------------------------------------------------
 * 1. WebAuthn Biometrics / Passkeys Tests
 * ------------------------------------------------------------- */

test('guest cannot request webauthn registration options', function () {
    $response = $this->getJson(route('webauthn.register.options'));
    $response->assertStatus(401);
});

test('authenticated user receives valid webauthn registration options', function () {
    $user = User::create([
        'name' => 'Agent007',
        'email' => 'agent007@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    $response = $this->actingAs($user)->getJson(route('webauthn.register.options'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'challenge',
            'rp' => ['name', 'id'],
            'user' => ['id', 'name', 'displayName'],
            'pubKeyCredParams',
            'timeout',
            'attestation',
        ]);
});

test('user can register a mock webauthn biometric credential', function () {
    $user = User::create([
        'name' => 'BioAgent',
        'email' => 'bio@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    // Populate registration challenge in session
    $challenge = random_bytes(32);
    $challengeBase64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');
    session(['webauthn_register_challenge' => $challengeBase64]);

    $clientData = [
        'type' => 'webauthn.create',
        'challenge' => $challengeBase64,
        'origin' => config('app.url'),
    ];
    $clientDataJSON = base64_encode(json_encode($clientData));

    // Construct raw mock authenticatorData (37 bytes minimum)
    $rpIdHash = hash('sha256', parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', true);
    $flags = chr(0x45); // UP (1) | UV (4) | AT (64)
    $signCount = pack('N', 0);
    $aaguid = str_repeat("\0", 16);
    $credId = random_bytes(16);
    $credIdLen = pack('n', 16);
    // Minimal COSE EC2 public key representation
    $coseKey = hex2bin('a5010203262001215820' . bin2hex(random_bytes(32)) . '225820' . bin2hex(random_bytes(32)));
    $authData = $rpIdHash . $flags . $signCount . $aaguid . $credIdLen . $credId . $coseKey;

    $payload = [
        'id' => rtrim(strtr(base64_encode($credId), '+/', '-_'), '='),
        'clientDataJSON' => $clientDataJSON,
        'attestationObject' => base64_encode($authData),
        'deviceName' => 'MacBook Pro Touch ID',
    ];

    $response = $this->actingAs($user)->postJson(route('webauthn.register'), $payload);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $credential = WebAuthnCredential::where('user_id', $user->id)->first();
    expect($credential)->not->toBeNull();
    expect($credential->device_name)->toBe('MacBook Pro Touch ID');
    expect($user->fresh()->hasBiometrics())->toBeTrue();
});

test('user can delete a registered biometric credential', function () {
    $user = User::create([
        'name' => 'RevokeAgent',
        'email' => 'revoke@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    $credential = WebAuthnCredential::create([
        'user_id' => $user->id,
        'credential_id' => 'test-cred-id-123',
        'public_key' => 'fake-public-key',
        'counter' => 0,
        'device_name' => 'iPad Face ID',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('webauthn.destroy', $credential->id));
    $response->assertStatus(200)->assertJson(['success' => true]);

    expect(WebAuthnCredential::find($credential->id))->toBeNull();
});

/* -------------------------------------------------------------
 * 2. Two-Factor Authentication (TOTP) Tests
 * ------------------------------------------------------------- */

test('authenticated user can initiate 2FA setup', function () {
    $user = User::create([
        'name' => 'TwoFactorAgent',
        'email' => '2fa@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    $response = $this->actingAs($user)->postJson(route('2fa.enable'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'secret',
            'qr_code_svg',
        ]);

    expect($response->json('secret'))->not->toBeNull();
    expect(strlen($response->json('secret')))->toBeGreaterThanOrEqual(16);
});

test('2FA confirmation rejects invalid 6-digit code', function () {
    $user = User::create([
        'name' => 'WrongCodeAgent',
        'email' => 'wrong@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();
    session(['2fa_setup_secret' => $secret]);

    $response = $this->actingAs($user)->postJson(route('2fa.confirm'), [
        'code' => '000000',
    ]);

    $response->assertStatus(422)
        ->assertJson(['success' => false]);

    expect($user->fresh()->hasTwoFactor())->toBeFalse();
});

test('2FA confirmation activates 2FA and returns 8 recovery codes', function () {
    Mail::fake();

    $user = User::create([
        'name' => 'Valid2FAAgent',
        'email' => 'valid2fa@vesper.test',
        'password' => Hash::make('secret-pass'),
    ]);

    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();
    $validCode = $twoFactorService->calculateTotp($secret);
    session(['2fa_setup_secret' => $secret]);

    $response = $this->actingAs($user)->postJson(route('2fa.confirm'), [
        'code' => $validCode,
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'recovery_codes',
        ]);

    $codes = $response->json('recovery_codes');
    expect(count($codes))->toBe(8);

    $freshUser = $user->fresh();
    expect($freshUser->hasTwoFactor())->toBeTrue();
    expect(\Illuminate\Support\Facades\Crypt::decryptString($freshUser->two_factor_secret))->toBe($secret);
    expect($freshUser->two_factor_recovery_codes)->not->toBeEmpty();
});

test('user with 2FA enabled is intercepted during login and redirected to challenge', function () {
    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();

    $user = User::create([
        'name' => 'GuardedAgent',
        'email' => 'guarded@vesper.test',
        'role' => 'admin',
        'password' => Hash::make('agent-password'),
        'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString($secret),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => [
            hash('sha256', 'RECOV-1111'),
            hash('sha256', 'RECOV-2222'),
        ],
    ]);

    $response = $this->post(route('login.post'), [
        'login' => 'GuardedAgent',
        'password' => 'agent-password',
    ]);

    $response->assertRedirect(route('2fa.challenge'));
    $this->assertGuest();
    expect(session('2fa_user_id'))->toBe($user->id);

    // Visit challenge page
    $challengePage = $this->get(route('2fa.challenge'));
    $challengePage->assertStatus(200);
    $challengePage->assertSee('Security Clearance Required');
});

test('user can complete 2FA challenge using TOTP code', function () {
    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();

    $user = User::create([
        'name' => 'TotpSolver',
        'email' => 'solver@vesper.test',
        'role' => 'admin',
        'password' => Hash::make('password123'),
        'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString($secret),
        'two_factor_confirmed_at' => now(),
    ]);

    session(['2fa_user_id' => $user->id]);

    $code = $twoFactorService->calculateTotp($secret);

    $response = $this->post(route('2fa.verify'), [
        'code' => $code,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(session('2fa_user_id'))->toBeNull();
});

test('user can complete 2FA challenge using single-use emergency recovery code', function () {
    Mail::fake();

    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();

    $rawCode = 'EMERG-1234-5678';
    $user = User::create([
        'name' => 'EmergencyBypassAgent',
        'email' => 'bypass@vesper.test',
        'role' => 'admin',
        'password' => Hash::make('password123'),
        'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString($secret),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => [
            password_hash(str_replace(['-', ' '], '', $rawCode), PASSWORD_DEFAULT),
            password_hash('ANOTHERCODE999', PASSWORD_DEFAULT),
        ],
    ]);

    session(['2fa_user_id' => $user->id]);

    $response = $this->post(route('2fa.verify'), [
        'recovery_code' => $rawCode,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);

    // Verify recovery code was consumed and removed
    $freshUser = $user->fresh();
    expect(count($freshUser->two_factor_recovery_codes))->toBe(1);
    expect($freshUser->consumeRecoveryCode($rawCode))->toBeFalse();
});

test('user can disable 2FA with current password confirmation', function () {
    $twoFactorService = app(TwoFactorService::class);
    $secret = $twoFactorService->generateSecretKey();

    $user = User::create([
        'name' => 'Disable2FAAgent',
        'email' => 'disable2fa@vesper.test',
        'password' => Hash::make('correct-password'),
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => [password_hash('CODE1', PASSWORD_DEFAULT)],
    ]);

    // Fails with wrong password
    $failResponse = $this->actingAs($user)->postJson(route('2fa.disable'), [
        'password' => 'wrong-password',
    ]);
    $failResponse->assertStatus(422);
    expect($user->fresh()->hasTwoFactor())->toBeTrue();

    // Succeeds with correct password
    $successResponse = $this->actingAs($user)->postJson(route('2fa.disable'), [
        'password' => 'correct-password',
    ]);
    $successResponse->assertStatus(200)->assertJson(['success' => true]);

    $freshUser = $user->fresh();
    expect($freshUser->hasTwoFactor())->toBeFalse();
    expect($freshUser->two_factor_secret)->toBeNull();
    expect($freshUser->two_factor_recovery_codes)->toBeNull();
});

/* -------------------------------------------------------------
 * 3. Secondary Recovery Email & Emergency Account Recovery Tests
 * ------------------------------------------------------------- */

test('user can update secondary recovery email and receive verification mail', function () {
    Mail::fake();

    $user = User::create([
        'name' => 'RecoverableUser',
        'email' => 'primary@vesper.test',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->actingAs($user)->postJson(route('recovery.email.update'), [
        'recovery_email' => 'secondary@secure.test',
    ]);

    $response->assertStatus(200)->assertJson(['success' => true]);

    $freshUser = $user->fresh();
    expect($freshUser->recovery_email)->toBe('secondary@secure.test');
    expect($freshUser->recovery_email_verified_at)->toBeNull();

    Mail::assertSent(RecoveryEmailVerification::class, function ($mail) {
        return $mail->hasTo('secondary@secure.test');
    });
});

test('user can verify secondary recovery email via signed URL', function () {
    $user = User::create([
        'name' => 'VerifyingUser',
        'email' => 'main@vesper.test',
        'password' => Hash::make('password123'),
        'recovery_email' => 'backup@domain.test',
    ]);

    $signedUrl = \Illuminate\Support\Facades\URL::signedRoute('recovery.email.verify', [
        'id' => $user->id,
        'hash' => sha1('backup@domain.test'),
    ]);

    $response = $this->actingAs($user)->get($signedUrl);
    $response->assertRedirect(route('admin.dashboard'));

    expect($user->fresh()->hasVerifiedRecoveryEmail())->toBeTrue();
});

test('emergency account recovery dispatches reset links to verified recovery email', function () {
    Mail::fake();

    $user = User::create([
        'name' => 'LockedOutAgent',
        'email' => 'primary-locked@vesper.test',
        'recovery_email' => 'verified-backup@secure.test',
        'recovery_email_verified_at' => now(),
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->post(route('recovery.send'), [
        'identifier' => 'primary-locked@vesper.test',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $freshUser = $user->fresh();
    expect($freshUser->recovery_token)->not->toBeNull();
    expect($freshUser->recovery_token_expires_at->isFuture())->toBeTrue();

    // Verify recovery email was sent
    Mail::assertSent(EmergencyAccountRecovery::class, function ($mail) {
        return $mail->hasTo('verified-backup@secure.test');
    });
});

test('emergency password reset updates user password and clears token', function () {
    Mail::fake();

    $token = Str::random(64);
    $user = User::create([
        'name' => 'PasswordResetAgent',
        'email' => 'reset-me@vesper.test',
        'password' => Hash::make('outdated-secret'),
        'recovery_token' => hash('sha256', $token),
        'recovery_token_expires_at' => now()->addMinutes(30),
    ]);

    // View reset form
    $viewResponse = $this->get(route('recovery.reset.form', ['token' => $token, 'email' => 'reset-me@vesper.test']));
    $viewResponse->assertStatus(200)->assertSee('Create New Access Password');

    // Submit new password
    $postResponse = $this->post(route('recovery.reset'), [
        'token' => $token,
        'email' => 'reset-me@vesper.test',
        'password' => 'BrandNewStrongPass99!',
        'password_confirmation' => 'BrandNewStrongPass99!',
    ]);

    $postResponse->assertRedirect(route('admin.dashboard'));

    $freshUser = $user->fresh();
    expect(Hash::check('BrandNewStrongPass99!', $freshUser->password))->toBeTrue();
    expect($freshUser->recovery_token)->toBeNull();
});

/* -------------------------------------------------------------
 * 4. OAuth Social Authentication Tests (Google & Apple)
 * ------------------------------------------------------------- */

test('google oauth redirect constructs valid google accounts URL', function () {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
        'services.google.redirect' => 'https://vesper.test/auth/google/callback',
    ]);

    $response = $this->get(route('oauth.redirect', 'google'));
    $response->assertRedirect();

    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('https://accounts.google.com/o/oauth2/v2/auth');
    expect($targetUrl)->toContain('client_id=test-google-id');
    expect($targetUrl)->toContain('scope=' . urlencode('openid email profile'));
});

test('apple oauth redirect constructs valid apple id URL', function () {
    config([
        'services.apple.client_id' => 'test-apple-service-id',
        'services.apple.client_secret' => 'test-apple-secret',
        'services.apple.redirect' => 'https://vesper.test/auth/apple/callback',
    ]);

    $response = $this->get(route('oauth.redirect', 'apple'));
    $response->assertRedirect();

    $targetUrl = $response->headers->get('Location');
    expect($targetUrl)->toContain('https://appleid.apple.com/auth/authorize');
    expect($targetUrl)->toContain('client_id=test-apple-service-id');
    expect($targetUrl)->toContain('response_mode=form_post');
});

test('google oauth callback links account and logs in existing user', function () {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
        'services.google.redirect' => 'https://vesper.test/auth/google/callback',
    ]);

    $user = User::create([
        'name' => 'OAuthExistingUser',
        'email' => 'oauth@gmail.com',
        'role' => 'admin',
        'password' => Hash::make('password123'),
    ]);

    session(['oauth_state_google' => 'valid-state-123']);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'mock-google-access-token',
            'id_token' => 'mock-id-token',
        ]),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-id-998877',
            'name' => 'OAuth Existing User',
            'email' => 'oauth@gmail.com',
        ]),
    ]);

    $response = $this->get(route('oauth.callback', [
        'provider' => 'google',
        'code' => 'mock-auth-code',
        'state' => 'valid-state-123',
    ]));

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);

    $socialAccount = SocialAccount::where('user_id', $user->id)->first();
    expect($socialAccount)->not->toBeNull();
    expect($socialAccount->provider)->toBe('google');
    expect($socialAccount->provider_id)->toBe('google-user-id-998877');
});
