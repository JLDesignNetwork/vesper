<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('authenticated user can upload a profile picture', function () {
    Storage::fake('public');

    $user = User::create([
        'name' => 'AgentJane',
        'email' => 'jane@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('jane_avatar.png', 200, 200);

    $response = $this->post(route('profile.update'), [
        'name' => 'AgentJane',
        'email' => 'jane@example.com',
        'avatar' => $file,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    expect($user->avatarUrl())->toContain('/storage/'.$user->avatar_path);

    Storage::disk('public')->assertExists($user->avatar_path);
});

test('authenticated user can remove their profile picture', function () {
    Storage::fake('public');

    $user = User::create([
        'name' => 'AgentMark',
        'email' => 'mark@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $this->actingAs($user);

    // Upload first
    $file = UploadedFile::fake()->image('mark.jpg', 150, 150);
    $this->post(route('profile.update'), [
        'name' => 'AgentMark',
        'email' => 'mark@example.com',
        'avatar' => $file,
    ], ['Accept' => 'application/json']);

    $user->refresh();
    $oldPath = $user->avatar_path;
    Storage::disk('public')->assertExists($oldPath);

    // Now remove
    $removeResponse = $this->post(route('profile.update'), [
        'name' => 'AgentMark',
        'email' => 'mark@example.com',
        'remove_avatar' => 1,
    ], ['Accept' => 'application/json']);

    $removeResponse->assertStatus(200);
    $removeResponse->assertJson(['success' => true]);

    $user->refresh();
    expect($user->avatar_path)->toBeNull();
    expect($user->avatarUrl())->toBeNull();
    Storage::disk('public')->assertMissing($oldPath);
});

test('avatar validation rejects non-image files and oversized files', function () {
    Storage::fake('public');

    $user = User::create([
        'name' => 'AgentTest',
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $this->actingAs($user);

    // Non-image file
    $txtFile = UploadedFile::fake()->create('malicious.txt', 100);
    $response = $this->post(route('profile.update'), [
        'name' => 'AgentTest',
        'email' => 'test@example.com',
        'avatar' => $txtFile,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['avatar']);

    // Oversized file (> 5MB)
    $largeImage = UploadedFile::fake()->create('huge.png', 6000);
    $responseLarge = $this->post(route('profile.update'), [
        'name' => 'AgentTest',
        'email' => 'test@example.com',
        'avatar' => $largeImage,
    ], ['Accept' => 'application/json']);

    $responseLarge->assertStatus(422);
    $responseLarge->assertJsonValidationErrors(['avatar']);
});

test('messages stream includes sender avatar url', function () {
    $user = User::create([
        'name' => 'AgentAvatar',
        'email' => 'avatar@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'avatar_path' => 'avatars/avatar_1_test.png',
    ]);

    $room = Room::create([
        'code' => 'AVTR-1234',
        'title' => 'Avatar Room',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    Message::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'sender_name' => $user->name,
        'sender_session_id' => 'session-avatar-test',
        'content' => 'Hello with avatar',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->get(route('messages.index', ['room' => $room->code]));

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['messages'])->toHaveCount(1);
    expect($data['messages'][0]['sender_avatar_url'])->toContain('avatars/avatar_1_test.png');
});

test('member profile endpoint returns user profile details and avatar url for chat card', function () {
    $user = User::create([
        'name' => 'AgentCardTest',
        'email' => 'cardtest@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'avatar_path' => 'avatars/avatar_card_test.jpg',
        'birthday' => '1990-05-15',
        'gender' => 'Other',
        'location' => 'Sector 7, Matrix',
        'bio' => 'Elite cryptographer.',
    ]);

    $room = Room::create([
        'code' => 'CARD-5678',
        'title' => 'Card Room',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'burn_after_reading' => false,
        'ttl_minutes' => 60,
        'created_by_ip' => '127.0.0.1',
        'status' => 'active',
    ]);

    // Without clearance -> 403
    $unauthResponse = $this->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentCardTest']));
    $unauthResponse->assertStatus(403);

    // With clearance -> 200 with avatar and profile
    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->getJson(route('rooms.member-profile', ['room' => $room->code, 'name' => 'AgentCardTest']));

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['found'])->toBeTrue();
    expect($data['name'])->toBe('AgentCardTest');
    expect($data['avatar_url'])->toContain('avatars/avatar_card_test.jpg');
    expect($data['age'])->toBeGreaterThan(0);
    expect($data['gender'])->toBe('Other');
    expect($data['location'])->toBe('Sector 7, Matrix');
    expect($data['bio'])->toBe('Elite cryptographer.');
});
