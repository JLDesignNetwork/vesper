<?php

use App\Enums\Rank;
use App\Mail\MemberWelcomeNotification;
use App\Mail\PrimaryEmailVerification;
use App\Mail\RecoveryEmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function verificationLinkFor(User $user, ?string $email = null, ?Closure $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        $expiresAt ? $expiresAt() : now()->addHours(24),
        ['id' => $user->id, 'hash' => sha1($email ?? $user->email)]
    );
}

test('registering sends a verification link to the new email address', function () {
    Mail::fake();

    $this->post(route('register.post'), [
        'name' => 'Nova',
        'email' => 'nova@vesper.test',
        'password' => 'secret123',
    ])->assertRedirect(route('profile.show'));

    $member = User::where('email', 'nova@vesper.test')->sole();
    expect($member->hasVerifiedEmail())->toBeFalse();
    expect($member->rank)->toBe(Rank::UserTwo);
    Mail::assertSent(PrimaryEmailVerification::class, fn (PrimaryEmailVerification $mail) => $mail->hasTo('nova@vesper.test')
        && str_contains($mail->verificationUrl, '/email/verify/'.$member->id.'/'));
});

test('the first account set up becomes admin one and is sent a verification link', function () {
    Mail::fake();

    $this->post(route('login.post'), [
        'name' => 'Owner',
        'email' => 'owner@vesper.test',
        'password' => 'secret-admin-pass',
    ])->assertRedirect(route('admin.dashboard'));

    $owner = User::where('email', 'owner@vesper.test')->sole();
    expect($owner->rank)->toBe(Rank::AdminOne);
    Mail::assertSent(PrimaryEmailVerification::class, fn (PrimaryEmailVerification $mail) => $mail->hasTo('owner@vesper.test'));
});

test('a verification link confirms the email without an active session', function () {
    $member = User::factory()->unverified()->create();

    $response = $this->get(verificationLinkFor($member));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Your email address is verified.');
    expect($member->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a verification link opened while signed in returns to the profile', function () {
    $member = User::factory()->unverified()->create();

    $this->actingAs($member)->get(verificationLinkFor($member))
        ->assertRedirect(route('profile.show'));

    expect($member->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('a verification link that cannot be trusted is refused', function (Closure $makeLink, string $error) {
    $member = User::factory()->unverified()->create(['email' => 'current@vesper.test']);

    $response = $this->get($makeLink($member));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email' => $error]);
    expect($member->fresh()->hasVerifiedEmail())->toBeFalse();
})->with([
    'tampered signature' => [
        fn (User $member) => verificationLinkFor($member).'tampered',
        'This email verification link is invalid or has expired. Request a new one from your profile.',
    ],
    'expired link' => [
        fn (User $member) => verificationLinkFor($member, expiresAt: fn () => now()->subMinute()),
        'This email verification link is invalid or has expired. Request a new one from your profile.',
    ],
    'link for an email the member has since changed' => [
        fn (User $member) => verificationLinkFor($member, email: 'previous@vesper.test'),
        'This email verification link does not match your current email address. Request a new one from your profile.',
    ],
]);

test('an unverified member can request a fresh verification link', function () {
    Mail::fake();
    $member = User::factory()->unverified()->create(['email' => 'pending@vesper.test']);

    $this->actingAs($member)->post(route('verification.send'))
        ->assertRedirect(route('profile.show'))
        ->assertSessionHas('status', 'A new verification link was sent to pending@vesper.test.');

    Mail::assertSent(PrimaryEmailVerification::class, fn (PrimaryEmailVerification $mail) => $mail->hasTo('pending@vesper.test'));
});

test('a verified member requesting a link is told no new link is needed', function () {
    Mail::fake();
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('verification.send'))
        ->assertSessionHas('status', 'Your email address is already verified.');

    Mail::assertNothingSent();
});

test('requesting a verification link requires signing in', function () {
    $this->post(route('verification.send'))->assertRedirect(route('login'));
});

test('the profile asks unverified members to verify their email', function (bool $verified, bool $bannerShown) {
    $member = $verified ? User::factory()->create() : User::factory()->unverified()->create();

    $response = $this->actingAs($member)->get(route('profile.show'))->assertOk();

    $bannerShown
        ? $response->assertSee('Verify your email address')->assertSee(route('verification.send'))
        : $response->assertDontSee('Verify your email address');
})->with([
    'unverified member sees the prompt' => [false, true],
    'verified member does not' => [true, false],
]);

test('the verification email contains the link and no unfilled placeholders', function () {
    $member = User::factory()->unverified()->make(['name' => 'Nova', 'email' => 'nova@vesper.test']);

    $html = (new PrimaryEmailVerification($member, 'https://vesper.test/email/verify/1/abc'))->render();

    expect($html)
        ->toContain('https://vesper.test/email/verify/1/abc')
        ->toContain('nova@vesper.test')
        ->not->toContain('{{');
});

test('the recovery email verification names the recovery address instead of a placeholder', function () {
    $member = User::factory()->make(['recovery_email' => 'backup@vesper.test']);

    $html = (new RecoveryEmailVerification($member, 'https://vesper.test/recovery/email/verify/1/abc'))->render();

    expect($html)->toContain('backup@vesper.test')->not->toContain('{{');
});

test('the welcome email states the public rank label instead of a placeholder', function () {
    $member = User::factory()->make();

    $html = (new MemberWelcomeNotification($member))->render();

    expect($html)->toContain('Member')->not->toContain('{{');
});

test('the verification email escapes the member name', function () {
    $member = User::factory()->unverified()->make(['name' => "O'Reilly <script>alert('x')</script>"]);

    $html = (new PrimaryEmailVerification($member, 'https://vesper.test/email/verify/1/abc'))->render();

    expect($html)
        ->toContain('&lt;script')
        ->not->toContain('<script');
});
