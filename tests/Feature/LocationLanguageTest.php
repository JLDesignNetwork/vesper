<?php

use App\Models\Room;
use App\Models\User;
use App\Services\GeoLocationService;
use Illuminate\Support\Facades\Hash;

test('GeoLocationService resolves common language from country code, country, or location text', function () {
    $geo = app(GeoLocationService::class);

    // Italian
    expect($geo->resolveLanguageFromLocation('IT'))->toBe('it');
    expect($geo->resolveLanguageFromLocation('SM'))->toBe('it');
    expect($geo->resolveLanguageFromLocation(null, 'Italy'))->toBe('it');
    expect($geo->resolveLanguageFromLocation(null, null, 'Rome, Italy'))->toBe('it');
    expect($geo->resolveLanguageFromLocation(null, null, 'Milano'))->toBe('it');

    // French
    expect($geo->resolveLanguageFromLocation('FR'))->toBe('fr');
    expect($geo->resolveLanguageFromLocation('MC'))->toBe('fr');
    expect($geo->resolveLanguageFromLocation(null, 'France'))->toBe('fr');
    expect($geo->resolveLanguageFromLocation(null, null, 'Paris, France'))->toBe('fr');
    expect($geo->resolveLanguageFromLocation(null, null, 'Marseille'))->toBe('fr');

    // Russian
    expect($geo->resolveLanguageFromLocation('RU'))->toBe('ru');
    expect($geo->resolveLanguageFromLocation('BY'))->toBe('ru');
    expect($geo->resolveLanguageFromLocation('KZ'))->toBe('ru');
    expect($geo->resolveLanguageFromLocation(null, 'Russia'))->toBe('ru');
    expect($geo->resolveLanguageFromLocation(null, null, 'Moscow, Russia'))->toBe('ru');
    expect($geo->resolveLanguageFromLocation(null, null, 'Saint Petersburg'))->toBe('ru');

    // English / Default
    expect($geo->resolveLanguageFromLocation('US'))->toBe('en');
    expect($geo->resolveLanguageFromLocation('GB'))->toBe('en');
    expect($geo->resolveLanguageFromLocation('CA'))->toBe('en');
    expect($geo->resolveLanguageFromLocation(null, 'United States'))->toBe('en');
    expect($geo->resolveLanguageFromLocation(null, null, 'London, UK'))->toBe('en');
    expect($geo->resolveLanguageFromLocation())->toBe('en');
});

test('user model resolves location locale and prefers user setting when configured', function () {
    // User in Italy without explicit language preference
    $italianUser = User::create([
        'name' => 'Marco',
        'email' => 'marco@example.com',
        'password' => Hash::make('password123'),
        'country_code' => 'IT',
        'country' => 'Italy',
        'location' => 'Rome, Italy',
        'preferred_locale' => null,
    ]);

    expect($italianUser->resolveLocationLocale())->toBe('it');
    expect($italianUser->effectiveLocale())->toBe('it');

    // Setting explicit preference overrides location language
    $italianUser->preferred_locale = 'fr';
    $italianUser->save();

    expect($italianUser->resolveLocationLocale())->toBe('it');
    expect($italianUser->effectiveLocale())->toBe('fr');

    // Resetting to null reverts to location language
    $italianUser->preferred_locale = null;
    $italianUser->save();

    expect($italianUser->effectiveLocale())->toBe('it');
});

test('application locale adapts to registered location on request', function () {
    $frenchUser = User::create([
        'name' => 'Pierre',
        'email' => 'pierre@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'country_code' => 'FR',
        'country' => 'France',
        'location' => 'Paris, France',
    ]);

    $this->actingAs($frenchUser);
    $response = $this->get(route('admin.dashboard'));

    $response->assertStatus(200);
    expect(app()->getLocale())->toBe('fr');
    expect(session('locale'))->toBe('fr');
});

test('user preferred language setting strictly overrides registered location on request', function () {
    $russianUserInRome = User::create([
        'name' => 'Dmitry',
        'email' => 'dmitry@example.com',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'country_code' => 'IT',
        'country' => 'Italy',
        'location' => 'Rome, Italy',
        'preferred_locale' => 'ru', // Overrides Italian location
    ]);

    $this->actingAs($russianUserInRome);
    $response = $this->get(route('admin.dashboard'));

    $response->assertStatus(200);
    expect(app()->getLocale())->toBe('ru');
    expect(session('locale'))->toBe('ru');
});

test('profile update endpoint validates and persists preferred_locale', function () {
    $user = User::create([
        'name' => 'Alessandro',
        'email' => 'alessandro@example.com',
        'password' => Hash::make('password123'),
        'location' => 'Florence, Italy',
        'country_code' => 'IT',
    ]);

    $this->actingAs($user);

    // 1. Select English override
    $res = $this->postJson(route('profile.update'), [
        'name' => 'Alessandro',
        'email' => 'alessandro@example.com',
        'preferred_locale' => 'en',
    ]);

    $res->assertStatus(200);
    $res->assertJson(['success' => true]);
    $user->refresh();
    expect($user->preferred_locale)->toBe('en');
    expect(session('locale'))->toBe('en');

    // 2. Switch back to auto
    $resAuto = $this->postJson(route('profile.update'), [
        'name' => 'Alessandro',
        'email' => 'alessandro@example.com',
        'preferred_locale' => 'auto',
    ]);

    $resAuto->assertStatus(200);
    $user->refresh();
    expect($user->preferred_locale)->toBeNull();
    // Since location is Florence, Italy, auto reverts to Italian
    expect(session('locale'))->toBe('it');
});

test('locale switcher route persists preferred_locale for authenticated users', function () {
    $user = User::create([
        'name' => 'Claire',
        'email' => 'claire@example.com',
        'password' => Hash::make('password123'),
        'location' => 'London, UK',
    ]);

    $this->actingAs($user);

    $this->get(route('locale.switch', ['locale' => 'fr']));
    $user->refresh();
    expect($user->preferred_locale)->toBe('fr');
    expect(session('locale'))->toBe('fr');

    // Switching to auto clears preferred_locale
    $this->get(route('locale.switch', ['locale' => 'auto']));
    $user->refresh();
    expect($user->preferred_locale)->toBeNull();
});

test('user registers account and immediately sets preferred language', function () {
    $response = $this->post(route('register.post'), [
        'name' => 'Matteo Rossi',
        'email' => 'matteo@example.com',
        'password' => 'secret123',
        'preferred_locale' => 'it',
        'location' => 'Milan, Italy',
    ]);

    $response->assertRedirect(route('profile.show'));
    $newUser = User::where('email', 'matteo@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->preferred_locale)->toBe('it');
    expect(session('locale'))->toBe('it');
    expect(app()->getLocale())->toBe('it');
});

test('every room automatically serves pre-translated messages in the user preferred language with options to switch', function () {
    $user = User::create([
        'name' => 'Elena Petrova',
        'email' => 'elena@example.com',
        'password' => Hash::make('password123'),
        'role' => 'member',
        'preferred_locale' => 'ru',
    ]);

    $room = Room::create([
        'code' => 'RU-TEST-ROOM',
        'name' => 'General Discussion',
        'is_locked' => false,
        'ephemeral_mode' => 'persistent',
        'passcode_hash' => Hash::make('1234'),
    ]);

    $this->actingAs($user);

    // Enter room
    $this->withSession(["room_clearance_{$room->id}" => true]);

    // Send a message in English
    $postRes = $this->postJson(route('messages.store', ['room' => $room->code]), [
        'content' => 'Welcome to our international conference.',
    ]);
    $postRes->assertStatus(200);

    // User polls messages with target_lang=ru (their preferred language)
    $fetchRes = $this->getJson(route('messages.index', ['room' => $room->code, 'target_lang' => 'ru']));
    $fetchRes->assertStatus(200);
    $data = $fetchRes->json('messages');
    expect($data)->not->toBeEmpty();
    expect($data[0]['auto_translated_lang'])->toBe('ru');
    expect($data[0]['auto_translated_text'])->not->toBeNull();

    // User can switch and translate to a different language (e.g. French)
    $transRes = $this->postJson(route('messages.translate', ['room' => $room->code]), [
        'message_id' => $data[0]['id'],
        'target_lang' => 'fr',
    ]);
    $transRes->assertStatus(200);
    $transData = $transRes->json();
    expect($transData['success'])->toBeTrue();
    expect($transData['target_lang'])->toBe('fr');
    expect($transData['translated_text'])->not->toBeNull();

    // User can change preferred language in profile to Italian
    $profileRes = $this->postJson(route('profile.update'), [
        'name' => 'Elena Petrova',
        'email' => 'elena@example.com',
        'preferred_locale' => 'it',
    ]);
    $profileRes->assertStatus(200);
    $user->refresh();
    expect($user->preferred_locale)->toBe('it');
    expect(session('locale'))->toBe('it');

    // Next room poll automatically uses Italian
    $fetchResIt = $this->getJson(route('messages.index', ['room' => $room->code, 'target_lang' => $user->preferred_locale]));
    $fetchResIt->assertStatus(200);
    $dataIt = $fetchResIt->json('messages');
    expect($dataIt[0]['auto_translated_lang'])->toBe('it');
});
