<?php

use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Models\Room;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\ChannelAccessService;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\FakePasskey;

/**
 * Enroll a software passkey through the real registration endpoints.
 */
function enrollFakePasskey(User $user): FakePasskey
{
    $passkey = new FakePasskey;
    $challenge = test()->actingAs($user)->getJson(route('webauthn.register.options'))->assertOk()->json('challenge');
    test()->actingAs($user)->postJson(route('webauthn.register'), $passkey->attestation($challenge))->assertOk();

    return $passkey;
}

function passkeyLogin(FakePasskey $passkey, array $overrides = [], array $clientDataOverrides = [])
{
    $challenge = test()->postJson(route('webauthn.login.options'))->json('challenge');

    return test()->postJson(route('webauthn.login.verify'), array_merge($passkey->assertion($challenge, $clientDataOverrides), $overrides));
}

function passkeyChannel(ChannelType $type, ?User $member = null): Room
{
    $room = Room::create([
        'code' => 'PKY-'.strtoupper(Str::random(4)),
        'type' => $type,
        'pin' => '4321',
        'status' => 'active',
        'is_system' => $type->isAdminChannel(),
    ]);

    if ($member) {
        $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active']);
    }

    return $room;
}

function openWithPasskey(User $user, Room $room, FakePasskey $passkey)
{
    $challenge = test()->actingAs($user)->postJson(route('rooms.passkey.options', $room->code))->assertOk()->json('challenge');

    return test()->actingAs($user)->postJson(route('rooms.passkey.verify', $room->code), $passkey->assertion($challenge));
}

function eligibleMember(array $attributes = []): User
{
    return User::factory()->withVerifiedRecoveryEmail()->create($attributes);
}

/* -------------------------------------------------------------
 * Passkey sign-in is verified on the server (AUTH-ISSUE-06)
 * ------------------------------------------------------------- */

test('a signed passkey assertion signs the member in', function () {
    $member = eligibleMember();
    $passkey = enrollFakePasskey($member);
    auth()->logout();

    passkeyLogin($passkey)->assertOk()->assertJsonPath('success', true);

    $this->assertAuthenticatedAs($member);
});

test('forged or replayed passkey assertions are refused', function (Closure $forge) {
    $member = eligibleMember();
    $passkey = enrollFakePasskey($member);
    auth()->logout();

    $forge($passkey)->assertUnauthorized()->assertJsonPath('success', false);

    $this->assertGuest();
})->with([
    'signed by another key' => [fn (FakePasskey $passkey) => passkeyLogin($passkey, ['signature' => (new FakePasskey)->assertion('x')['signature']])],
    'no signature' => [fn (FakePasskey $passkey) => passkeyLogin($passkey, ['signature' => ''])],
    'no challenge was issued' => [fn (FakePasskey $passkey) => test()->postJson(route('webauthn.login.verify'), $passkey->assertion(Str::random(32)))],
    'no challenge issued and none answered' => [fn (FakePasskey $passkey) => test()->postJson(route('webauthn.login.verify'), $passkey->assertion('', ['challenge' => '']))],
    'signed for another relying party' => [function (FakePasskey $passkey) {
        $challenge = test()->postJson(route('webauthn.login.options'))->json('challenge');

        return test()->postJson(route('webauthn.login.verify'), $passkey->assertion($challenge, [], null, 'evil.example'));
    }],
    'without user presence' => [function (FakePasskey $passkey) {
        $challenge = test()->postJson(route('webauthn.login.options'))->json('challenge');

        return test()->postJson(route('webauthn.login.verify'), $passkey->assertion($challenge, [], null, null, 0x04));
    }],
    'a different challenge' => [fn (FakePasskey $passkey) => passkeyLogin($passkey, [], ['challenge' => FakePasskey::base64Url(random_bytes(24))])],
    'another site' => [fn (FakePasskey $passkey) => passkeyLogin($passkey, [], ['origin' => 'https://evil.example'])],
    'a registration response' => [fn (FakePasskey $passkey) => passkeyLogin($passkey, [], ['type' => 'webauthn.create'])],
    'a replayed challenge' => [function (FakePasskey $passkey) {
        $challenge = test()->postJson(route('webauthn.login.options'))->json('challenge');
        test()->postJson(route('webauthn.login.verify'), $passkey->assertion($challenge))->assertOk();
        auth()->logout();

        return test()->postJson(route('webauthn.login.verify'), $passkey->assertion($challenge));
    }],
    'a counter that did not increase' => [function (FakePasskey $passkey) {
        passkeyLogin($passkey)->assertOk();
        auth()->logout();
        $challenge = test()->postJson(route('webauthn.login.options'))->json('challenge');

        return test()->postJson(route('webauthn.login.verify'), $passkey->assertion($challenge, [], 1));
    }],
]);

test('a passkey enrolled in the old format is upgraded on first use', function () {
    $member = eligibleMember();
    $passkey = new FakePasskey;
    $legacy = $passkey->attestation('legacy-challenge');
    WebAuthnCredential::create(['user_id' => $member->id, 'credential_id' => $passkey->id(), 'public_key' => $legacy['attestationObject'], 'counter' => 0, 'device_name' => 'Old key']);

    passkeyLogin($passkey)->assertOk();

    expect(WebAuthnCredential::sole()->public_key)->toStartWith('-----BEGIN PUBLIC KEY-----');
    $this->assertAuthenticatedAs($member);
});

/* -------------------------------------------------------------
 * Enrollment
 * ------------------------------------------------------------- */

test('a passkey can be enrolled only after the biometric precondition is met', function (Closure $member, bool $allowed) {
    $response = $this->actingAs($member())->getJson(route('webauthn.register.options'));

    $allowed ? $response->assertOk() : $response->assertUnprocessable()->assertJsonPath('success', false);
})->with([
    'verified email and verified recovery email' => [fn () => eligibleMember(), true],
    'verified email and 2FA' => [fn () => User::factory()->create(['two_factor_secret' => Crypt::encryptString(app(TwoFactorService::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()]), true],
    'verified email only' => [fn () => User::factory()->create(), false],
    'unverified email with recovery email' => [fn () => User::factory()->unverified()->withVerifiedRecoveryEmail()->create(), false],
]);

test('the refusal lists what is missing', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->getJson(route('webauthn.register.options'))
        ->assertJsonPath('missing', ['Verified primary email', 'Verified recovery email or Two-Factor Authentication']);
});

test('a registration response is refused when it does not verify', function (Closure $tamper) {
    $member = eligibleMember();
    $passkey = new FakePasskey;
    $challenge = $this->actingAs($member)->getJson(route('webauthn.register.options'))->json('challenge');

    $this->actingAs($member)->postJson(route('webauthn.register'), $tamper($passkey, $challenge))->assertUnprocessable();

    expect(WebAuthnCredential::count())->toBe(0);
})->with([
    'wrong challenge' => [fn (FakePasskey $passkey, string $challenge) => $passkey->attestation(Str::random(32))],
    'another site' => [fn (FakePasskey $passkey, string $challenge) => $passkey->attestation($challenge, ['origin' => 'https://evil.example'])],
    'mismatched credential id' => [fn (FakePasskey $passkey, string $challenge) => array_merge($passkey->attestation($challenge), ['id' => FakePasskey::base64Url(random_bytes(16))])],
    'no attestation object' => [fn (FakePasskey $passkey, string $challenge) => array_merge($passkey->attestation($challenge), ['attestationObject' => base64_encode('not cbor')])],
]);

test('registering a credential id another member owns does not take it over', function () {
    $owner = eligibleMember();
    $passkey = enrollFakePasskey($owner);
    $attacker = eligibleMember();

    $challenge = $this->actingAs($attacker)->getJson(route('webauthn.register.options'))->json('challenge');
    $this->actingAs($attacker)->postJson(route('webauthn.register'), $passkey->attestation($challenge))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This passkey is already registered.');

    expect(WebAuthnCredential::sole()->user_id)->toBe($owner->id);
});

/* -------------------------------------------------------------
 * pra: a fresh passkey check on every entry (AU-10)
 * ------------------------------------------------------------- */

test('pra shows only the passkey check, never a PIN form', function () {
    $adminTwo = eligibleMember();
    $adminTwo->forceFill(['rank' => Rank::AdminTwo])->save();
    enrollFakePasskey($adminTwo);
    $room = passkeyChannel(ChannelType::PrivateAdmin);

    $this->actingAs($adminTwo)->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertSee('id="passkey-entry-btn"', false)
        ->assertDontSee('id="passcode"', false);
});

test('a passkey check opens pra, and the PIN form cannot', function () {
    $adminTwo = eligibleMember();
    $adminTwo->forceFill(['rank' => Rank::AdminTwo])->save();
    $passkey = enrollFakePasskey($adminTwo);
    $room = passkeyChannel(ChannelType::PrivateAdmin);

    $this->actingAs($adminTwo)->post(route('rooms.verify', $room->code), ['passcode' => '4321'])->assertSessionHasErrors('passcode');
    $this->actingAs($adminTwo)->getJson(route('messages.index', $room->code))->assertForbidden();

    openWithPasskey($adminTwo, $room, $passkey)->assertOk()->assertJsonPath('redirect', route('rooms.show', $room->code));

    $this->actingAs($adminTwo)->getJson(route('messages.index', $room->code))->assertOk();
});

test('pra needs a new passkey check once clearance lapses', function () {
    $adminOne = eligibleMember();
    $adminOne->forceFill(['rank' => Rank::AdminOne])->save();
    $passkey = enrollFakePasskey($adminOne);
    $room = passkeyChannel(ChannelType::PrivateAdmin);
    openWithPasskey($adminOne, $room, $passkey)->assertOk();

    $this->travel(ChannelAccessService::CLEARANCE_IDLE_MINUTES + 1)->minutes();

    $this->actingAs($adminOne)->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertSee('id="passkey-entry-btn"', false);
});

test('another member\'s passkey cannot open pra', function () {
    $adminTwo = eligibleMember();
    $adminTwo->forceFill(['rank' => Rank::AdminTwo])->save();
    enrollFakePasskey($adminTwo);
    $other = eligibleMember();
    $otherPasskey = enrollFakePasskey($other);
    $room = passkeyChannel(ChannelType::PrivateAdmin);

    $challenge = $this->actingAs($adminTwo)->postJson(route('rooms.passkey.options', $room->code))->json('challenge');
    $this->actingAs($adminTwo)->postJson(route('rooms.passkey.verify', $room->code), $otherPasskey->assertion($challenge))->assertUnprocessable();

    $this->actingAs($adminTwo)->getJson(route('messages.index', $room->code))->assertForbidden();
});

/* -------------------------------------------------------------
 * PIN-secured channels: passkey as an alternative, 2FA pin-free (A1)
 * ------------------------------------------------------------- */

test('an eligible member may open a PIN-secured channel with a passkey check instead of the PIN', function () {
    $member = eligibleMember();
    $passkey = enrollFakePasskey($member);
    $room = passkeyChannel(ChannelType::PrivateSecured, $member);

    $this->actingAs($member)->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertSee('id="passkey-entry-btn"', false)
        ->assertSee('id="passcode"', false);

    openWithPasskey($member, $room, $passkey)->assertOk();
    $this->actingAs($member)->getJson(route('messages.index', $room->code))->assertOk();
});

test('a passkey alone no longer opens a PIN-secured channel without a check', function () {
    $member = eligibleMember();
    enrollFakePasskey($member);
    $room = passkeyChannel(ChannelType::PublicSecured, $member);

    expect(app(ChannelAccessService::class)->entryFor($member, $room)->value)->toBe('pin_required');
});

test('members who do not meet the biometric precondition cannot use a passkey to open channels', function () {
    $member = User::factory()->create();
    enrollPasskey($member);
    $room = passkeyChannel(ChannelType::PrivateSecured, $member);

    expect(app(ChannelAccessService::class)->allowsPasskeyEntry($member, $room))->toBeFalse();
    $this->actingAs($member)->postJson(route('rooms.passkey.options', $room->code))->assertForbidden();
});

test('members can require the PIN for one channel, which turns off 2FA and passkey entry there', function () {
    $member = eligibleMember(['two_factor_secret' => Crypt::encryptString(app(TwoFactorService::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()]);
    enrollFakePasskey($member);
    $room = passkeyChannel(ChannelType::PrivateSecured, $member);
    $access = app(ChannelAccessService::class);
    expect($access->entryFor($member, $room->fresh())->value)->toBe('granted');

    $this->actingAs($member)->post(route('channels.pinless-entry', $room->id), ['enabled' => 0])->assertRedirect()->assertSessionHas('status');

    expect((bool) DB::table('room_user')->where('room_id', $room->id)->where('user_id', $member->id)->value('biometric_entry'))->toBeFalse();
    expect($access->entryFor($member, $room)->value)->toBe('pin_required');
    expect($access->allowsPasskeyEntry($member, $room))->toBeFalse();

    $this->actingAs($member)->post(route('channels.pinless-entry', $room->id), ['enabled' => 1]);
    expect($access->entryFor($member, $room)->value)->toBe('granted');
});

test('the pin-free switch is only for members of PIN-secured channels', function (ChannelType $type, bool $isMember, string $outcome) {
    $user = User::factory()->adminTwo()->create();
    $room = passkeyChannel($type, $isMember ? $user : null);

    $response = $this->actingAs($user)->post(route('channels.pinless-entry', $room->id), ['enabled' => 0]);

    $outcome === 'not found'
        ? $response->assertNotFound()
        : $response->assertSessionHasErrors(['channel' => 'This channel has no PIN, so there is nothing to switch.']);
    expect(DB::table('room_user')->where('room_id', $room->id)->where('user_id', $user->id)->value('biometric_entry'))->not->toBe(0);
})->with([
    'not a member' => [ChannelType::PrivateSecured, false, 'not found'],
    'pra' => [ChannelType::PrivateAdmin, true, 'refused'],
    'free-entry channel' => [ChannelType::PrivateFreeEntry, true, 'refused'],
]);

test('the profile shows the pin-free switch on PIN-secured channels', function () {
    $member = eligibleMember();
    enrollFakePasskey($member);
    $room = passkeyChannel(ChannelType::PrivateSecured, $member);

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertSee(route('channels.pinless-entry', $room->id), false)
        ->assertSee('Opens with 2FA or passkey');
});
