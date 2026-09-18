<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Hash;

test('all 15 supported languages have complete 642-key translation dictionaries', function () {
    $codes = LanguageService::codes();
    expect($codes)->toHaveCount(15);
    expect($codes)->toContain('en', 'it', 'fr', 'ru', 'es', 'de', 'pt', 'ja', 'ko', 'zh', 'uz', 'ar', 'tr', 'nl', 'pl');

    $enPath = base_path('lang/en.json');
    expect(file_exists($enPath))->toBeTrue();
    $enKeys = array_keys(json_decode(file_get_contents($enPath), true));
    $expectedCount = count($enKeys);

    foreach ($codes as $code) {
        $path = base_path("lang/{$code}.json");
        expect(file_exists($path))->toBeTrue("Missing lang/{$code}.json");

        $data = json_decode(file_get_contents($path), true);
        expect(is_array($data))->toBeTrue("Corrupted lang/{$code}.json");
        expect(count($data))->toBe($expectedCount, "Key count mismatch in lang/{$code}.json");

        // Verify zero missing keys compared to en.json
        $missing = array_diff($enKeys, array_keys($data));
        expect($missing)->toBeEmpty("Keys missing in lang/{$code}.json: ".implode(', ', $missing));
    }
});

test('user can register with newly supported languages such as uzbek or japanese', function () {
    $responseUz = $this->post(route('register'), [
        'name' => 'UzbekMember',
        'email' => 'uzbek@vesper.test',
        'password' => 'secret123',
        'preferred_locale' => 'uz',
    ]);

    $responseUz->assertRedirect(route('profile.show'));
    $userUz = User::where('email', 'uzbek@vesper.test')->first();
    expect($userUz)->not->toBeNull();
    expect($userUz->preferred_locale)->toBe('uz');
    expect($userUz->effectiveLocale())->toBe('uz');
    expect(session('locale'))->toBe('uz');

    $responseJa = $this->post(route('register'), [
        'name' => 'JapaneseMember',
        'email' => 'japanese@vesper.test',
        'password' => 'secret123',
        'preferred_locale' => 'ja',
    ]);

    $responseJa->assertRedirect(route('profile.show'));
    $userJa = User::where('email', 'japanese@vesper.test')->first();
    expect($userJa)->not->toBeNull();
    expect($userJa->preferred_locale)->toBe('ja');
    expect($userJa->effectiveLocale())->toBe('ja');
    expect(session('locale'))->toBe('ja');
});

test('user profile update validates and persists all supported languages', function () {
    $user = User::create([
        'name' => 'PolyglotUser',
        'email' => 'polyglot@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'preferred_locale' => 'en',
    ]);

    foreach (['es', 'de', 'pt', 'ko', 'zh', 'ar', 'tr', 'nl', 'pl'] as $targetLang) {
        $response = $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'PolyglotUser',
            'email' => 'polyglot@vesper.test',
            'preferred_locale' => $targetLang,
        ]);

        $response->assertSessionHasNoErrors();
        expect($user->fresh()->preferred_locale)->toBe($targetLang);
        expect($user->fresh()->effectiveLocale())->toBe($targetLang);
    }
});

test('geolocation maps country codes to newly supported languages accurately', function () {
    $geo = app(GeoLocationService::class);

    expect($geo->resolveLanguageFromLocation('UZ'))->toBe('uz');
    expect($geo->resolveLanguageFromLocation('JP'))->toBe('ja');
    expect($geo->resolveLanguageFromLocation('KR'))->toBe('ko');
    expect($geo->resolveLanguageFromLocation('CN'))->toBe('zh');
    expect($geo->resolveLanguageFromLocation('ES'))->toBe('es');
    expect($geo->resolveLanguageFromLocation('DE'))->toBe('de');
    expect($geo->resolveLanguageFromLocation('BR'))->toBe('pt');
    expect($geo->resolveLanguageFromLocation('SA'))->toBe('ar');
    expect($geo->resolveLanguageFromLocation('TR'))->toBe('tr');
    expect($geo->resolveLanguageFromLocation('NL'))->toBe('nl');
    expect($geo->resolveLanguageFromLocation('PL'))->toBe('pl');
});

test('translation service accurately translates text into newly supported languages', function () {
    $service = new TranslationService;

    $uzResult = $service->translate('Channel List', 'uz');
    expect($uzResult['success'])->toBeTrue();
    expect($uzResult['translated_text'])->not->toBeEmpty();
    expect($uzResult['target_lang'])->toBe('uz');

    $jaResult = $service->translate('Channel List', 'ja');
    expect($jaResult['success'])->toBeTrue();
    expect($jaResult['translated_text'])->not->toBeEmpty();
    expect($jaResult['target_lang'])->toBe('ja');

    $esResult = $service->translate('Channel List', 'es');
    expect($esResult['success'])->toBeTrue();
    expect($esResult['translated_text'])->not->toBeEmpty();
    expect($esResult['target_lang'])->toBe('es');
});

test('channel translate and stream endpoints serve translations in uzbek', function () {
    $room = Room::create([
        'code' => 'UZ-TEST',
        'title' => 'Uzbek Channel',
        'pin' => '123456',
        'passcode_hash' => Hash::make('123456'),
        'status' => 'active',
    ]);

    $user = User::create([
        'name' => 'UzbekViewer',
        'email' => 'uzviewer@vesper.test',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'preferred_locale' => 'uz',
    ]);

    $room->addMember($user, 'member');

    // Post to translate endpoint
    $response = $this->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->postJson("/c/{$room->code}/translate", [
            'text' => 'Hello friend, welcome to Vesper.',
            'target' => 'uz',
        ]);

    $response->assertStatus(200);
    $data = $response->json();
    expect($data['success'])->toBeTrue();
    expect($data['target_lang'])->toBe('uz');
    expect($data['translated_text'])->not->toBeEmpty();

    // Stream messages query
    $msg = Message::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'sender_name' => 'Grant',
        'sender_session_id' => 'other-session',
        'content' => 'Testing translation into Uzbek.',
    ]);

    $streamResponse = $this->actingAs($user)
        ->withSession(["room_clearance_{$room->id}" => true])
        ->getJson("/c/{$room->code}/messages?target_lang=uz");

    $streamResponse->assertStatus(200);
    $streamData = $streamResponse->json();
    expect($streamData['messages'])->toHaveCount(1);
    expect($streamData['messages'][0]['auto_translated_lang'])->toBe('uz');
});
