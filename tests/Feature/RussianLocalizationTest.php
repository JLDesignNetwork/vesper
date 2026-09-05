<?php

use App\Models\Room;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

test('portal switches to Russian language via query parameter or session', function () {
    $response = $this->get('/?lang=ru');

    $response->assertStatus(200);
    $response->assertSee('Sunday City');
    $response->assertSee('Имя пользователя');

    expect(session('locale'))->toBe('ru');
});

test('locale switch endpoint changes the session locale and redirects back', function () {
    $response = $this->get(route('locale.switch', ['locale' => 'ru']));

    $response->assertStatus(302);
    expect(session('locale'))->toBe('ru');

    $responseEn = $this->get(route('locale.switch', ['locale' => 'en']));
    $responseEn->assertStatus(302);
    expect(session('locale'))->toBe('en');
});

test('message translation endpoint returns translated text into Russian', function () {
    Http::fake([
        'https://clients5.google.com/*' => Http::response([
            ['Секретная встреча в полночь', 'en'],
        ], 200),
        'https://api.mymemory.translated.net/*' => Http::response([
            'responseData' => [
                'translatedText' => 'Секретная встреча в полночь',
            ],
        ], 200),
    ]);

    $room = Room::create([
        'code' => 'TRANS-01',
        'passcode_hash' => Hash::make('secret'),
        'status' => 'active',
    ]);

    $response = $this->withSession([
        "room_clearance_{$room->id}" => true,
    ])->postJson(route('messages.translate', ['room' => 'TRANS-01']), [
        'text' => 'Secret meeting at midnight',
        'target' => 'ru',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('translated_text', 'Секретная встреча в полночь');
});

test('translation service handles empty string and cyrillic source text gracefully', function () {
    $service = new TranslationService;

    $empty = $service->translate('');
    expect($empty['translated_text'])->toBe('');

    Http::fake([
        'https://clients5.google.com/*' => Http::response([
            ['Hello comrade', 'ru'],
        ], 200),
        'https://api.mymemory.translated.net/*' => Http::response([
            'responseData' => [
                'translatedText' => 'Hello comrade',
            ],
        ], 200),
    ]);

    $fromRussian = $service->translate('Привет товарищ', 'ru');
    expect($fromRussian['target_lang'])->toBe('en');
    expect($fromRussian['translated_text'])->toBe('Hello comrade');
});

