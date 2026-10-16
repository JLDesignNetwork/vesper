<?php

use App\Enums\Rank;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Build an unsigned JWT whose payload carries the given claims, the way an attacker
 * would forge an Apple id_token.
 *
 * @param  array<string, mixed>  $claims
 */
function forgedAppleIdToken(array $claims): string
{
    $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

    return $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode($claims).'.forged-signature';
}

function configureAppleOAuth(): void
{
    config([
        'services.apple.client_id' => 'test-apple-service-id',
        'services.apple.client_secret' => 'test-apple-secret',
        'services.apple.redirect' => 'https://vesper.test/auth/apple/callback',
    ]);
}

test('a forged apple id token in the callback url does not sign in as an existing user', function () {
    configureAppleOAuth();
    $owner = User::factory()->adminOne()->create(['email' => 'owner@vesper.test']);

    $response = $this->get(route('oauth.callback', [
        'provider' => 'apple',
        'id_token' => forgedAppleIdToken(['sub' => 'attacker-sub', 'email' => 'owner@vesper.test', 'email_verified' => true]),
    ]));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(SocialAccount::where('user_id', $owner->id)->exists())->toBeFalse();
});

test('a forged apple id token posted with a matching state does not sign in as an existing user', function () {
    configureAppleOAuth();
    $owner = User::factory()->adminOne()->create(['email' => 'owner@vesper.test']);
    Http::fake([
        'https://appleid.apple.com/auth/token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    $response = $this->withSession(['oauth_state_apple' => 'state-abc'])->post(route('oauth.callback.post', 'apple'), [
        'state' => 'state-abc',
        'code' => 'stolen-or-invented-code',
        'id_token' => forgedAppleIdToken(['sub' => 'attacker-sub', 'email' => 'owner@vesper.test']),
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(SocialAccount::where('user_id', $owner->id)->exists())->toBeFalse();
});

test('an oauth callback without the state issued at redirect is rejected', function (?string $sentState) {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
    ]);
    User::factory()->create(['email' => 'victim@gmail.com']);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'g-1', 'email' => 'victim@gmail.com']),
    ]);

    $response = $this->withSession(['oauth_state_google' => 'issued-state'])
        ->get(route('oauth.callback', array_filter(['provider' => 'google', 'code' => 'code', 'state' => $sentState])));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['login' => 'Authentication with Google failed: The sign-in request expired or did not come from this browser. Please try again.']);
    $this->assertGuest();
    Http::assertNothingSent();
})->with([
    'missing state' => [null],
    'mismatched state' => ['attacker-state'],
]);

test('an oauth callback for a provider that is not configured is rejected', function () {
    config(['services.apple.client_id' => null, 'services.apple.client_secret' => null]);
    User::factory()->create(['email' => 'owner@vesper.test']);

    $response = $this->withSession(['oauth_state_apple' => 'state-abc'])->post(route('oauth.callback.post', 'apple'), [
        'state' => 'state-abc',
        'code' => 'code',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('apple sign in trusts only the id token returned by the apple token endpoint', function () {
    configureAppleOAuth();
    $member = User::factory()->unverified()->create(['email' => 'member@icloud.com']);
    Http::fake([
        'https://appleid.apple.com/auth/token' => Http::response([
            'id_token' => forgedAppleIdToken([
                'iss' => 'https://appleid.apple.com',
                'aud' => 'test-apple-service-id',
                'sub' => 'apple-sub-1',
                'email' => 'member@icloud.com',
                'email_verified' => 'true',
            ]),
        ]),
    ]);

    $response = $this->withSession(['oauth_state_apple' => 'state-abc'])->post(route('oauth.callback.post', 'apple'), [
        'state' => 'state-abc',
        'code' => 'genuine-code',
        'id_token' => forgedAppleIdToken(['sub' => 'attacker-sub', 'email' => 'someone-else@vesper.test']),
    ]);

    $response->assertRedirect(route('profile.show'));
    $this->assertAuthenticatedAs($member);
    expect(SocialAccount::where('user_id', $member->id)->value('provider_id'))->toBe('apple-sub-1');
    expect($member->fresh()->hasVerifiedEmail())->toBeTrue();
    Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token'
        && $request['code'] === 'genuine-code'
        && $request['grant_type'] === 'authorization_code');
});

test('an apple token issued for a different application is rejected', function () {
    configureAppleOAuth();
    User::factory()->create(['email' => 'member@icloud.com']);
    Http::fake([
        'https://appleid.apple.com/auth/token' => Http::response([
            'id_token' => forgedAppleIdToken([
                'iss' => 'https://appleid.apple.com',
                'aud' => 'some-other-app',
                'sub' => 'apple-sub-1',
                'email' => 'member@icloud.com',
            ]),
        ]),
    ]);

    $response = $this->withSession(['oauth_state_apple' => 'state-abc'])->post(route('oauth.callback.post', 'apple'), [
        'state' => 'state-abc',
        'code' => 'code-for-other-app',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the state is single use so a captured callback cannot be replayed', function () {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
    ]);
    User::factory()->create(['email' => 'member@gmail.com']);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'g-2', 'email' => 'member@gmail.com']),
    ]);

    $this->withSession(['oauth_state_google' => 'issued-state'])
        ->get(route('oauth.callback', ['provider' => 'google', 'code' => 'code', 'state' => 'issued-state']));
    $this->post(route('logout'));

    $replay = $this->get(route('oauth.callback', ['provider' => 'google', 'code' => 'code', 'state' => 'issued-state']));

    $replay->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a google sign in marks the email verified only when google confirms it', function (bool $googleConfirms, bool $expectedVerified) {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
    ]);
    $member = User::factory()->unverified()->create(['email' => 'member@gmail.com']);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'g-3',
            'email' => 'member@gmail.com',
            'email_verified' => $googleConfirms,
        ]),
    ]);

    $this->withSession(['oauth_state_google' => 'issued-state'])
        ->get(route('oauth.callback', ['provider' => 'google', 'code' => 'code', 'state' => 'issued-state']));

    $this->assertAuthenticatedAs($member);
    expect($member->fresh()->hasVerifiedEmail())->toBe($expectedVerified);
})->with([
    'google confirms the email' => [true, true],
    'google does not confirm the email' => [false, false],
]);

test('admin one signing in with google lands on the admin dashboard', function () {
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
    ]);
    $owner = User::factory()->rank(Rank::AdminOne)->create(['email' => 'owner@gmail.com']);
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['sub' => 'g-4', 'email' => 'owner@gmail.com']),
    ]);

    $response = $this->withSession(['oauth_state_google' => 'issued-state'])
        ->get(route('oauth.callback', ['provider' => 'google', 'code' => 'code', 'state' => 'issued-state']));

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($owner);
});
