<?php

use App\Models\User;
use App\Models\Room;
use App\Services\GeoLocationService;
use Illuminate\Support\Facades\Auth;
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
