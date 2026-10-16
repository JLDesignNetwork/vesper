<?php

use App\Enums\ChannelType;
use App\Models\Message;
use App\Models\MessagePin;
use App\Models\MessageUserView;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A free-entry channel owned by $creator, so pinning and reading need no PIN.
 */
function pinChannel(User $creator, array $attributes = []): Room
{
    $room = Room::create(array_merge([
        'code' => 'PIN-'.strtoupper(Str::random(4)),
        'type' => ChannelType::PrivateFreeEntry,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
    ], $attributes));
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    return $room;
}

function postInChannel(Room $room, User $sender, array $attributes = []): Message
{
    return Message::create(array_merge([
        'room_id' => $room->id,
        'user_id' => $sender->id,
        'sender_session_id' => 'session-'.$sender->id,
        'sender_name' => $sender->name,
        'content' => 'Message from '.$sender->name,
    ], $attributes));
}

function pinAs(User $user, Room $room, Message $message)
{
    return test()->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson(route('rooms.pin', ['room' => $room->code, 'message' => $message->id]));
}

function pollAs(User $user, Room $room)
{
    return test()->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson(route('messages.index', $room->code));
}

/* -------------------------------------------------------------
 * Pinning and the 10-pin limit
 * ------------------------------------------------------------- */

test('a channel holds up to ten pins and refuses the eleventh', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $messages = collect(range(1, 11))->map(fn ($i) => postInChannel($room, $creator, ['content' => "Note {$i}"]));

    $messages->take(10)->each(fn (Message $message) => pinAs($creator, $room, $message)->assertOk()->assertJsonPath('is_pinned', true));

    pinAs($creator, $room, $messages->last())
        ->assertUnprocessable()
        ->assertJsonPath('error', 'This channel already has 10 pinned messages. Unpin one first.');

    expect(MessagePin::where('room_id', $room->id)->count())->toBe(10);
});

test('pinning a pinned message unpins it', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $message = postInChannel($room, $creator);
    pinAs($creator, $room, $message);

    pinAs($creator, $room, $message)
        ->assertOk()
        ->assertJsonPath('is_pinned', false)
        ->assertJsonPath('pinned_messages', []);

    expect($room->pinnedMessageIds())->toBe([]);
});

test('the pinned tray lists pins with the most recently pinned first', function () {
    $creator = User::factory()->create(['name' => 'Chief']);
    $room = pinChannel($creator);
    $first = postInChannel($room, $creator, ['content' => 'First rule']);
    $second = postInChannel($room, $creator, ['content' => 'Second rule']);
    pinAs($creator, $room, $first);
    pinAs($creator, $room, $second);

    pollAs($creator, $room)
        ->assertOk()
        ->assertJsonPath('pinned_messages.0.id', $second->id)
        ->assertJsonPath('pinned_messages.0.content', 'Second rule')
        ->assertJsonPath('pinned_messages.0.sender_name', 'Chief')
        ->assertJsonPath('pinned_messages.1.id', $first->id)
        ->assertJsonPath('pinned_message.id', $second->id);
});

test('a message that already self-destructed cannot be pinned', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $burned = postInChannel($room, $creator, ['ttl_seconds' => 10]);
    $burned->update(['expires_at' => now()->subMinute()]);

    pinAs($creator, $room, $burned)
        ->assertUnprocessable()
        ->assertJsonPath('error', 'This message has already self-destructed and cannot be pinned.');

    expect($room->pinnedMessageIds())->toBe([]);
});

test('a message from another channel cannot be pinned here', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $elsewhere = postInChannel(pinChannel($creator), $creator);

    pinAs($creator, $room, $elsewhere)->assertNotFound();
});

/* -------------------------------------------------------------
 * Pinned messages are never auto-deleted
 * ------------------------------------------------------------- */

test('a pinned message survives its per-reader self-destruct timer', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $reader = memberOf($room);
    $pinned = postInChannel($room, $creator, ['content' => 'Keep me', 'ttl_seconds' => 10]);
    $unpinned = postInChannel($room, $creator, ['content' => 'Burn me', 'ttl_seconds' => 10]);
    pinAs($creator, $room, $pinned);

    pollAs($reader, $room)->assertOk();
    $this->travel(30)->seconds();
    $messages = collect(pollAs($reader, $room)->json('messages'))->keyBy('id');

    expect($messages->has($unpinned->id))->toBeFalse();
    expect($messages[$pinned->id]['content'])->toBe('Keep me');
    expect($messages[$pinned->id]['text_burned'])->toBeFalse();
    expect($messages[$pinned->id]['is_pinned'])->toBeTrue();
    expect($messages[$pinned->id]['expires_at'])->toBeNull();
    expect(MessageUserView::where('message_id', $pinned->id)->exists())->toBeFalse();
});

test('a pinned message whose channel-wide timer has passed is still shown', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $pinned = postInChannel($room, $creator, ['content' => 'Standing order']);
    pinAs($creator, $room, $pinned);
    $pinned->update(['expires_at' => now()->subHour()]);
    $control = postInChannel($room, $creator, ['content' => 'Gone']);
    $control->update(['expires_at' => now()->subHour()]);

    $ids = collect(pollAs($creator, $room)->json('messages'))->pluck('id');

    expect($ids->contains($pinned->id))->toBeTrue();
    expect($ids->contains($control->id))->toBeFalse();
});

test('pinned media stays downloadable after its media timer passes', function () {
    Storage::fake('local');
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $reader = memberOf($room);
    $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')->store("attachments/{$room->id}", 'local');
    $pinned = postInChannel($room, $creator, ['content' => null, 'attachment_path' => $path, 'attachment_name' => 'brief.pdf', 'attachment_mime' => 'application/pdf', 'media_ttl_seconds' => 10]);
    $control = postInChannel($room, $creator, ['content' => null, 'attachment_path' => $path, 'attachment_name' => 'other.pdf', 'attachment_mime' => 'application/pdf', 'media_ttl_seconds' => 10]);
    pinAs($creator, $room, $pinned);

    pollAs($reader, $room);
    $this->travel(30)->seconds();

    $this->get(route('messages.attachment', [$room->code, $pinned->id]))->assertOk();
    $this->get(route('messages.attachment', [$room->code, $control->id]))->assertStatus(410);
});

test('pinning media after a reader started its countdown keeps it downloadable', function () {
    Storage::fake('local');
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $reader = memberOf($room);
    $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')->store("attachments/{$room->id}", 'local');
    $media = postInChannel($room, $creator, ['content' => null, 'attachment_path' => $path, 'attachment_name' => 'brief.pdf', 'attachment_mime' => 'application/pdf', 'media_ttl_seconds' => 10]);

    pollAs($reader, $room);
    expect(MessageUserView::where('message_id', $media->id)->whereNotNull('media_expires_at')->exists())->toBeTrue();
    pinAs($creator, $room, $media)->assertOk();
    $this->travel(30)->seconds();

    $this->actingAs($reader)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('messages.attachment', [$room->code, $media->id]))
        ->assertOk();
});

test('burn after reading does not mark pinned messages as read', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator, ['burn_after_reading' => true]);
    $reader = memberOf($room);
    $pinned = postInChannel($room, $creator);
    $control = postInChannel($room, $creator);
    pinAs($creator, $room, $pinned);

    pollAs($reader, $room)->assertOk();

    expect($pinned->fresh()->is_burn_read)->toBeFalse();
    expect($control->fresh()->is_burn_read)->toBeTrue();
});

test('unpinning resumes the message timers', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $pinned = postInChannel($room, $creator);
    pinAs($creator, $room, $pinned);
    $pinned->update(['expires_at' => now()->subMinute()]);

    pinAs($creator, $room, $pinned)->assertJsonPath('is_pinned', false);

    $ids = collect(pollAs($creator, $room)->json('messages'))->pluck('id');
    expect($ids->contains($pinned->id))->toBeFalse();
});

test('purging a channel removes its pinned messages too', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);
    $pinned = postInChannel($room, $creator);
    pinAs($creator, $room, $pinned);

    $this->actingAs($creator)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->post(route('rooms.nuke', $room->code))
        ->assertRedirect();

    expect(Message::find($pinned->id))->toBeNull();
    expect(MessagePin::count())->toBe(0);
});

test('the channel page shows the pinned tray', function () {
    $creator = User::factory()->create();
    $room = pinChannel($creator);

    $this->actingAs($creator)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->get(route('rooms.show', $room->code))
        ->assertOk()
        ->assertSee('id="pinned-tray"', false)
        ->assertSee('aria-controls="pinned-tray-list"', false);
});
