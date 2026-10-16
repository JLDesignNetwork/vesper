<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a channel owned by $owner with $verifiedMembers active members who have a
 * verified email (the owner is enrolled too, and is never counted toward ranks).
 *
 * @param  array<string, mixed>  $roomAttributes
 */
function channelOwnedBy(
    User $owner,
    int $verifiedMembers = RankService::MIN_VERIFIED_MEMBERS,
    ?Carbon $lastMessageAt = null,
    array $roomAttributes = [],
): Room {
    $room = Room::create(array_merge([
        'code' => strtoupper(Str::random(8)),
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'created_by_user_id' => $owner->id,
    ], $roomAttributes));

    $room->belongsToMany(User::class, 'room_user')->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

    User::factory()->count($verifiedMembers)->create()->each(
        fn (User $member) => $room->belongsToMany(User::class, 'room_user')->attach($member->id, ['role' => 'member', 'status' => 'active'])
    );

    $message = Message::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'sender_session_id' => 'owner-session',
        'sender_name' => $owner->name,
        'content' => 'Channel activity',
    ]);
    $message->forceFill(['created_at' => $lastMessageAt ?? now()])->save();

    return $room;
}

/**
 * Enroll a biometric passkey for the user.
 */
function enrollPasskey(User $user, string $deviceName = 'Touch ID'): WebAuthnCredential
{
    return WebAuthnCredential::create([
        'user_id' => $user->id,
        'credential_id' => 'cred-'.Str::random(12),
        'public_key' => 'test-public-key',
        'counter' => 0,
        'device_name' => $deviceName,
    ]);
}
