<?php

use App\Enums\AuditAction;
use App\Enums\Rank;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\WebAuthnCredential;

/**
 * A member who meets every Admin II prerequisite: verified email, verified recovery
 * email and an enrolled passkey.
 */
function eligibleAdminTwoCandidate(array $attributes = []): User
{
    $candidate = User::factory()->withVerifiedRecoveryEmail()->create($attributes);
    enrollPasskey($candidate);

    return $candidate;
}

test('admin one appoints an eligible member as admin two', function () {
    $owner = User::factory()->adminOne()->create();
    $candidate = eligibleAdminTwoCandidate(['name' => 'Sentinel']);

    $response = $this->actingAs($owner)->post(route('admin.users.admin-two.appoint', $candidate->id));

    $response->assertRedirect(route('admin.members.index'));
    $response->assertSessionHas('status', 'Sentinel is now Admin II.');
    expect($candidate->fresh()->rank)->toBe(Rank::AdminTwo);
    $entry = AdminAuditLog::sole();
    expect($entry->action)->toBe(AuditAction::AdminTwoAppointed);
    expect($entry->actor_user_id)->toBe($owner->id);
    expect($entry->subject_user_id)->toBe($candidate->id);
});

test('two factor authentication can stand in for a verified recovery email', function () {
    $owner = User::factory()->adminOne()->create();
    $candidate = User::factory()->create();
    $candidate->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();
    enrollPasskey($candidate);

    $this->actingAs($owner)->post(route('admin.users.admin-two.appoint', $candidate->id));

    expect($candidate->fresh()->rank)->toBe(Rank::AdminTwo);
});

test('appointing admin two is refused with the prerequisites still missing', function (Closure $makeCandidate, string $missing) {
    $owner = User::factory()->adminOne()->create();
    $candidate = $makeCandidate();

    $response = $this->actingAs($owner)->post(route('admin.users.admin-two.appoint', $candidate->id));

    $response->assertRedirect(route('admin.members.index'));
    $response->assertSessionHasErrors(['rank' => "Candidate cannot be appointed Admin II yet. Missing: {$missing}."]);
    expect($candidate->fresh()->rank)->toBe(Rank::UserTwo);
    expect(AdminAuditLog::count())->toBe(0);
})->with([
    'nothing set up' => [
        fn () => User::factory()->unverified()->create(['name' => 'Candidate']),
        'Verified primary email, Verified recovery email or Two-Factor Authentication, Enrolled biometric passkey',
    ],
    'no passkey' => [
        fn () => User::factory()->withVerifiedRecoveryEmail()->create(['name' => 'Candidate']),
        'Enrolled biometric passkey',
    ],
    'no recovery email or 2FA' => [
        function () {
            $candidate = User::factory()->create(['name' => 'Candidate']);
            enrollPasskey($candidate);

            return $candidate;
        },
        'Verified recovery email or Two-Factor Authentication',
    ],
    'primary email not verified' => [
        fn () => eligibleAdminTwoCandidate(['name' => 'Candidate', 'email_verified_at' => null]),
        'Verified primary email',
    ],
]);

test('admin two cannot appoint another admin two', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    $candidate = eligibleAdminTwoCandidate();

    $this->actingAs($adminTwo)->post(route('admin.users.admin-two.appoint', $candidate->id))
        ->assertForbidden();

    expect($candidate->fresh()->rank)->toBe(Rank::UserTwo);
});

test('admin one cannot change their own rank to admin two', function () {
    $owner = User::factory()->adminOne()->create();
    enrollPasskey($owner);

    $this->actingAs($owner)->post(route('admin.users.admin-two.appoint', $owner->id))
        ->assertForbidden();

    expect($owner->fresh()->rank)->toBe(Rank::AdminOne);
});

test('revoking admin two returns the member to the rank their channels earn', function () {
    $owner = User::factory()->adminOne()->create();
    $adminTwo = User::factory()->adminTwo()->create(['name' => 'Sentinel']);
    foreach (range(1, 3) as $i) {
        channelOwnedBy($adminTwo);
    }

    $response = $this->actingAs($owner)->delete(route('admin.users.admin-two.revoke', $adminTwo->id));

    $response->assertSessionHas('status', 'Sentinel is no longer Admin II and is now User I.');
    expect($adminTwo->fresh()->rank)->toBe(Rank::UserOne);
    $entry = AdminAuditLog::sole();
    expect($entry->action)->toBe(AuditAction::AdminTwoRevoked);
    expect($entry->details)->toBe(['to' => 'user_one']);
});

test('revoking admin two from a member who is not admin two is refused', function () {
    $owner = User::factory()->adminOne()->create();
    $member = User::factory()->create(['name' => 'Plain']);

    $this->actingAs($owner)->delete(route('admin.users.admin-two.revoke', $member->id))
        ->assertSessionHasErrors(['rank' => 'Plain is not Admin II.']);

    expect(AdminAuditLog::count())->toBe(0);
});

test('admin two cannot remove their only passkey', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    $passkey = enrollPasskey($adminTwo);

    $this->actingAs($adminTwo)->deleteJson(route('webauthn.destroy', $passkey->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Admin II requires an enrolled biometric key. Register another key before removing this one.');

    expect(WebAuthnCredential::whereKey($passkey->id)->exists())->toBeTrue();
});

test('admin two can remove a passkey while another remains', function () {
    $adminTwo = User::factory()->adminTwo()->create();
    $passkey = enrollPasskey($adminTwo, 'Old laptop');
    enrollPasskey($adminTwo, 'Phone');

    $this->actingAs($adminTwo)->deleteJson(route('webauthn.destroy', $passkey->id))->assertOk();

    expect(WebAuthnCredential::whereKey($passkey->id)->exists())->toBeFalse();
});

test('the members panel offers appointment to members and revocation to admin two', function () {
    $owner = User::factory()->adminOne()->create();
    $member = User::factory()->create();
    $adminTwo = User::factory()->adminTwo()->create();

    $this->actingAs($owner)->get(route('admin.members.index'))
        ->assertOk()
        ->assertSee(route('admin.users.admin-two.appoint', $member->id))
        ->assertSee(route('admin.users.admin-two.revoke', $adminTwo->id))
        ->assertDontSee(route('admin.users.admin-two.appoint', $owner->id));
});
