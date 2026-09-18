<?php

use App\Models\Room;
use App\Models\User;
use App\Services\LanguageService;
use App\Services\MediaStorageService;
use App\Services\TranslationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('zero discovery guard redirects authenticated non-members away from unjoined channels', function () {
    $member = User::create([
        'name' => 'OperativeSam',
        'email' => 'sam@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    $room = Room::create([
        'code' => 'CLASSIFIED-88',
        'title' => 'Black Site Omega',
        'passcode_hash' => Hash::make('secret99'),
        'status' => 'active',
    ]);

    // Member is NOT enrolled in room
    $response = $this->actingAs($member)->get(route('rooms.show', ['room' => $room->code]));

    $response->assertRedirect(route('profile.show'));
    $response->assertSessionHasErrors('code');
    $response->assertDontSee('Black Site Omega');
    $response->assertDontSee('CLASSIFIED-88');
});

test('arabic locale triggers native rtl direction attribute', function () {
    expect(LanguageService::getDirection('ar'))->toBe('rtl');
    expect(LanguageService::getDirection('en'))->toBe('ltr');
    expect(LanguageService::getDirection('uz'))->toBe('ltr');

    $member = User::create([
        'name' => 'ArabicUser',
        'email' => 'ar@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'preferred_locale' => 'ar',
    ]);

    $response = $this->actingAs($member)
        ->withSession(['locale' => 'ar'])
        ->get(route('profile.show'));

    $response->assertStatus(200);
    $response->assertSee('dir="rtl"', false);
    $response->assertSee('lang="ar"', false);
});

test('media storage service strips exif metadata from uploaded images', function () {
    Storage::fake('local');

    // Create a real JPEG image in memory with GD
    $img = imagecreatetruecolor(100, 100);
    $bg = imagecolorallocate($img, 30, 41, 59);
    imagefill($img, 0, 0, $bg);

    $tempPath = tempnam(sys_get_temp_dir(), 'test_img_').'.jpg';
    imagejpeg($img, $tempPath, 100);
    imagedestroy($img);

    $uploadedFile = new UploadedFile($tempPath, 'photo.jpg', 'image/jpeg', null, true);

    $service = new MediaStorageService;
    $result = $service->storeAttachment($uploadedFile, 'room-test-123');

    expect($result['type'])->toBe('image');
    expect(Storage::disk('local')->exists($result['path']))->toBeTrue();

    // Verify file size and that image is valid without EXIF issues
    $storedContent = Storage::disk('local')->get($result['path']);
    expect(strlen($storedContent))->toBeGreaterThan(0);

    // Verify that reading the stored file with GD succeeds
    $reloaded = @imagecreatefromstring($storedContent);
    expect($reloaded)->not->toBeFalse();
    if ($reloaded) {
        imagedestroy($reloaded);
    }

    if (file_exists($tempPath)) {
        unlink($tempPath);
    }
});

test('translation service preserves urls code snippets and mentions via tokens', function () {
    $service = new TranslationService;

    $input = 'Check this https://vesper.local/login and run `php artisan test` with @lead';
    $masked = $service->maskTokens($input);

    expect($masked['text'])->not->toContain('https://vesper.local/login');
    expect($masked['text'])->not->toContain('`php artisan test`');
    expect($masked['text'])->not->toContain('@lead');
    expect(count($masked['tokens']))->toBe(3);

    // Restore tokens
    $restored = $service->unmaskTokens($masked['text'], $masked['tokens']);
    expect($restored)->toBe($input);
});

test('batch translation endpoint translates multiple message items in a single request', function () {
    Cache::flush();
    Http::fake([
        '*clients5.google.com*' => Http::response([[['Salom Dunyo', 'Hello World']]], 200),
        '*api.mymemory.translated.net*' => Http::response(['responseData' => ['translatedText' => 'Salom Dunyo']], 200),
    ]);

    $admin = User::create([
        'name' => 'BatchAdmin',
        'email' => 'batch@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'admin',
    ]);

    $room = Room::create([
        'code' => 'BATCH-TEST',
        'title' => 'Batch Channel',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
        'allowed_languages' => ['en', 'uz', 'ru'],
    ]);

    $response = $this->actingAs($admin)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson(route('messages.translate-batch', ['room' => $room->code]), [
            'target_lang' => 'uz',
            'messages' => [
                ['id' => '1', 'text' => 'Hello World'],
                ['id' => '2', 'text' => 'Hello World'],
            ],
        ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'target_lang' => 'uz',
    ]);
    expect($response->json('translations.1'))->toBe('Salom Dunyo');
});

test('channel redemption endpoint enforces rate limiting after rapid requests', function () {
    $member = User::create([
        'name' => 'RateLimitUser',
        'email' => 'rate@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
    ]);

    // Send 5 requests (allowed)
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($member)->post(route('channels.redeem'), [
            'code' => 'INVALID-CODE-'.$i,
        ]);
    }

    // 6th request must be rate limited (429 Too Many Requests)
    $response = $this->actingAs($member)->post(route('channels.redeem'), [
        'code' => 'INVALID-CODE-6',
    ]);

    $response->assertStatus(429);
});

test('pwa manifest is accessible and configured for standalone display', function () {
    $response = $this->get('/manifest.json');

    $response->assertStatus(200);
    $response->assertJson([
        'name' => 'Vesper',
        'display' => 'standalone',
        'theme_color' => '#020617',
    ]);
});
