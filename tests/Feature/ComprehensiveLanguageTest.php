<?php

use App\Enums\Rank;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\BanVoteService;
use App\Services\GeoLocationService;
use App\Services\LanguageService;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Hash;

test('all 16 supported languages have complete translation dictionaries', function () {
    $codes = LanguageService::codes();
    expect($codes)->toHaveCount(16);
    expect($codes)->toContain('en', 'it', 'fr', 'ru', 'es', 'de', 'pt', 'ja', 'ko', 'zh', 'uz', 'ar', 'tr', 'nl', 'pl', 'ms');

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
    $user = User::forceCreate([
        'name' => 'PolyglotUser',
        'email' => 'polyglot@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
        'preferred_locale' => 'en',
    ]);

    foreach (['es', 'de', 'pt', 'ko', 'zh', 'ar', 'tr', 'nl', 'pl', 'ms'] as $targetLang) {
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
    expect($geo->resolveLanguageFromLocation('MY'))->toBe('ms');
    expect($geo->resolveLanguageFromLocation('BN'))->toBe('ms');
    expect($geo->resolveLanguageFromLocation('SG'))->toBe('en');
    expect($geo->resolveLanguageFromLocation(null, 'Malaysia'))->toBe('ms');
    expect($geo->resolveLanguageFromLocation(null, null, 'Kuala Lumpur'))->toBe('ms');
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

    $user = User::forceCreate([
        'name' => 'UzbekViewer',
        'email' => 'uzviewer@vesper.test',
        'password' => Hash::make('password123'),
        'rank' => Rank::UserTwo,
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

test('channel refactor strings are translated in every language', function (string $key) {
    $en = json_decode(file_get_contents(base_path('lang/en.json')), true);

    foreach (LanguageService::codes() as $code) {
        $data = json_decode(file_get_contents(base_path("lang/{$code}.json")), true);
        expect($data)->toHaveKey($key);

        if ($code !== 'en') {
            expect($data[$key])->not->toBe($en[$key], "lang/{$code}.json leaves \"{$key}\" untranslated");
        }
    }
})->with([
    'Blocked Members',
    'Your account is restricted pending an admin review.',
    'Ban Reviews',
    'Channel profile',
    'Remove from channel',
]);

test('plural forms of the refactor strings follow each language\'s rules', function (string $locale, int $count, string $expected) {
    app()->setLocale($locale);

    expect(trans_choice(':count member|:count members', $count, ['count' => $count]))->toBe($expected);
})->with([
    'English one' => ['en', 1, '1 member'],
    'English many' => ['en', 5, '5 members'],
    'Russian few' => ['ru', 3, '3 участника'],
    'Russian many' => ['ru', 5, '5 участников'],
    'Polish few' => ['pl', 3, '3 członkowie'],
    'Arabic two' => ['ar', 2, 'عضوان'],
    'Japanese' => ['ja', 7, 'メンバー 7 人'],
    'Malay' => ['ms', 5, '5 ahli'],
]);

test('a restricted member sees the restriction notice in their language', function () {
    $member = User::factory()->create(['preferred_locale' => 'de']);
    app(BanVoteService::class)->open(User::factory()->adminOne()->create(), $member, 'x');

    $this->actingAs($member)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Dein Konto ist bis zu einer Admin-Prüfung eingeschränkt.');
});

test('translated text inside page scripts is escaped for JavaScript, not HTML', function () {
    $response = $this->get(route('login', ['lang' => 'fr']))->assertOk();

    $response->assertSee("alert('Erreur d\\u0027authentification biométrique ou annulée.')", false);
    $response->assertDontSee('Erreur d&#039;authentification', false);
});

test('a member in Malaysia sees the interface in Malay', function () {
    $member = User::factory()->create(['preferred_locale' => null, 'country_code' => 'MY', 'country' => 'Malaysia']);

    expect($member->effectiveLocale())->toBe('ms');

    $this->actingAs($member)
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('lang="ms"', false)
        ->assertSee('Saluran Anda');
});

test('a channel that allowed every language before Malay also allows Malay', function (array $allowed, bool $allowsMalay) {
    $room = Room::create(['code' => 'MS-'.count($allowed), 'title' => 'Languages', 'status' => 'active', 'allowed_languages' => $allowed]);

    expect($room->supportsLanguage('ms'))->toBe($allowsMalay);
})->with([
    'all 15 earlier languages' => [['en', 'it', 'fr', 'ru', 'es', 'de', 'pt', 'ja', 'ko', 'zh', 'uz', 'ar', 'tr', 'nl', 'pl'], true],
    'a chosen subset' => [['en', 'uz', 'ru'], false],
]);
