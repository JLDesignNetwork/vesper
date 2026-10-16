<?php

use App\Enums\ChannelType;
use App\Mail\PrimaryEmailVerification;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\UserTagService;
use Illuminate\Support\Facades\Mail;

/* -------------------------------------------------------------
 * Tag generation
 * ------------------------------------------------------------- */

test('every new member is given a tag generated from their name', function (string $name, string $expectedTag) {
    expect(User::factory()->create(['name' => $name])->tag)->toBe($expectedTag);
})->with([
    'plain name' => ['Ana Lee', 'ana_lee'],
    'accents are transliterated' => ['José Müller', 'jose_muller'],
    'leading digits get a prefix' => ['007 Agent', 'member_007_agent'],
    'short names are padded' => ['Al', 'al0'],
    'symbols only' => ['!!!', 'member'],
    'reserved word is avoided' => ['Admin', 'admin_1'],
]);

test('a tag already taken gets a numeric suffix', function () {
    User::factory()->create(['name' => 'Ana Lee']);
    User::factory()->create(['name' => 'Ana Lee']);

    expect(User::factory()->create(['name' => 'ana lee'])->tag)->toBe('ana_lee_3');
});

test('mentions are found in text but not inside email addresses', function () {
    $tags = app(UserTagService::class)->mentionedTags([
        'Ping @Ana_Lee and @bob_k, mail ana@example.com, @x too short',
        null,
        'again @ana_lee',
    ]);

    expect($tags)->toBe(['ana_lee', 'bob_k']);
});

/* -------------------------------------------------------------
 * Editing a tag
 * ------------------------------------------------------------- */

function profilePayload(User $user, array $overrides = []): array
{
    return array_merge(['name' => $user->name, 'email' => $user->email], $overrides);
}

test('a member can change their tag and input is normalized', function () {
    $member = User::factory()->create();

    $this->actingAs($member)
        ->postJson(route('profile.update'), profilePayload($member, ['tag' => '@Night_Owl']))
        ->assertOk()
        ->assertJsonPath('user.tag', 'night_owl');

    expect($member->fresh()->tag)->toBe('night_owl');
});

test('an unusable tag is refused', function (string $tag, string $message) {
    User::factory()->create(['tag' => 'taken_tag']);
    $member = User::factory()->create(['tag' => 'original']);

    $this->actingAs($member)
        ->postJson(route('profile.update'), profilePayload($member, ['tag' => $tag]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tag' => $message]);

    expect($member->fresh()->tag)->toBe('original');
})->with([
    'too short' => ['ab', 'The tag field format is invalid.'],
    'starts with a digit' => ['9lives', 'The tag field format is invalid.'],
    'contains a hyphen' => ['night-owl', 'The tag field format is invalid.'],
    'reserved word' => ['admin', 'The selected tag is invalid.'],
    'already taken' => ['Taken_Tag', 'The tag has already been taken.'],
    'empty' => ['', 'The tag field is required.'],
]);

test('saving the profile without a tag keeps the current one', function () {
    $member = User::factory()->create(['tag' => 'keep_me']);

    $this->actingAs($member)->postJson(route('profile.update'), profilePayload($member))->assertOk();

    expect($member->fresh()->tag)->toBe('keep_me');
});

test('changing the primary email clears verification and sends a new link', function () {
    Mail::fake();
    $member = User::factory()->create(['email' => 'old@vesper.test']);

    $this->actingAs($member)
        ->postJson(route('profile.update'), profilePayload($member, ['email' => 'new@vesper.test']))
        ->assertOk();

    expect($member->fresh()->hasVerifiedEmail())->toBeFalse();
    Mail::assertSent(PrimaryEmailVerification::class, fn (PrimaryEmailVerification $mail) => $mail->hasTo('new@vesper.test'));
});

test('saving the profile with the same email keeps it verified', function () {
    Mail::fake();
    $member = User::factory()->create(['email' => 'same@vesper.test']);

    $this->actingAs($member)
        ->postJson(route('profile.update'), profilePayload($member, ['email' => 'SAME@vesper.test']))
        ->assertOk();

    expect($member->fresh()->hasVerifiedEmail())->toBeTrue();
    Mail::assertNothingSent();
});

/* -------------------------------------------------------------
 * Mentions in channels
 * ------------------------------------------------------------- */

function mentionChannel(): Room
{
    return Room::create(['code' => 'MEN-1', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active']);
}

test('only tags of channel members are confirmed for highlighting', function () {
    $room = mentionChannel();
    $reader = memberOf($room);
    memberOf($room, ['tag' => 'ana_lee']);
    User::factory()->create(['tag' => 'outsider']);
    $room->inviteUser(User::factory()->create(['tag' => 'invitee']));
    Message::create([
        'room_id' => $room->id,
        'sender_session_id' => 's',
        'sender_name' => 'X',
        'content' => 'Hey @Ana_Lee, @outsider, @invitee and @ghost_user',
    ]);

    $this->actingAs($reader)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('messages.index', $room->code))
        ->assertOk()
        ->assertJsonPath('mention_tags', ['ana_lee']);
});

test('the channel creator counts as a member for mentions', function () {
    $creator = User::factory()->create(['tag' => 'the_founder']);
    $room = Room::create(['code' => 'MEN-2', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active', 'created_by_user_id' => $creator->id]);
    $reader = memberOf($room);
    Message::create(['room_id' => $room->id, 'sender_session_id' => 's', 'sender_name' => 'X', 'content' => 'Thanks @the_founder']);

    $this->actingAs($reader)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('messages.index', $room->code))
        ->assertJsonPath('mention_tags', ['the_founder']);
});

test('sending a message returns the member tags it mentions', function () {
    $room = mentionChannel();
    $sender = memberOf($room);
    memberOf($room, ['tag' => 'ana_lee']);

    $this->actingAs($sender)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson(route('messages.store', $room->code), ['content' => 'cc @ana_lee @outsider'])
        ->assertOk()
        ->assertJsonPath('mention_tags', ['ana_lee']);
});

test('a highlighted tag opens the member card of a channel member', function () {
    $room = mentionChannel();
    $reader = memberOf($room);
    memberOf($room, ['tag' => 'ana_lee', 'name' => 'Ana Lee']);

    $this->actingAs($reader)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'tag' => '@Ana_Lee']))
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('name', 'Ana Lee')
        ->assertJsonPath('tag', 'ana_lee');
});

test('a tag of someone outside the channel does not open a member card', function () {
    $room = mentionChannel();
    $reader = memberOf($room);
    User::factory()->create(['tag' => 'outsider']);

    $this->actingAs($reader)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'tag' => 'outsider']))
        ->assertOk()
        ->assertJsonPath('found', false);
});

/* -------------------------------------------------------------
 * Member search
 * ------------------------------------------------------------- */

test('members can be found by tag prefix or name with public details only', function () {
    User::factory()->create(['tag' => 'night_owl', 'name' => 'Nora', 'bio' => 'secret bio', 'hide_bio' => true]);
    User::factory()->create(['tag' => 'nightingale', 'name' => 'Flo']);
    User::factory()->create(['tag' => 'zed', 'name' => 'Night Watch']);
    User::factory()->create(['tag' => 'other', 'name' => 'Someone']);

    $response = $this->actingAs(User::factory()->create(['tag' => 'searcher']))
        ->getJson(route('members.search', ['q' => '@Night']))
        ->assertOk();

    expect(collect($response->json('members'))->pluck('tag')->all())->toBe(['night_owl', 'nightingale', 'zed']);
    expect(array_keys($response->json('members.0')))->toBe(['tag', 'name', 'avatar_url']);
    $response->assertDontSee('secret bio')->assertDontSee('email');
});

test('member search needs at least two characters', function () {
    User::factory()->create(['tag' => 'night_owl']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('members.search', ['q' => 'n']))
        ->assertOk()
        ->assertJsonPath('members', []);
});

test('member search requires signing in', function () {
    $this->getJson(route('members.search', ['q' => 'night']))->assertUnauthorized();
});

test('a percent sign cannot turn member search into a full listing', function () {
    User::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('members.search', ['q' => '%%']))
        ->assertOk()
        ->assertJsonPath('members', []);
});

test('the profile shows the member their tag', function () {
    $member = User::factory()->create(['tag' => 'night_owl']);

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertSee('value="night_owl"', false)
        ->assertSee('@night_owl')
        ->assertSee(route('members.search'));
});

test('the member card looks names up only among the channel\'s members', function () {
    $creator = User::factory()->create();
    $room = Room::create(['code' => 'CARD-1', 'type' => ChannelType::PrivateFreeEntry, 'status' => 'active', 'created_by_user_id' => $creator->id]);
    $room->addMember($creator, 'owner');
    $member = memberOf($room, ['name' => 'Inside Member']);
    User::factory()->create(['name' => 'Outside Person', 'bio' => 'Not your business']);

    $card = fn (string $name) => $this->actingAs($member)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => $name]));

    $card('Inside Member')->assertJsonPath('found', true);
    $card('Outside Person')->assertJsonPath('found', false)->assertJsonMissing(['bio' => 'Not your business']);
});
