<?php

use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IntelController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WebAuthnController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Public Root & Authentication Entry Point
Route::get('/', [AuthController::class, 'showLogin'])->name('portal');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// WebAuthn / Biometrics (Passkeys)
Route::post('/webauthn/login/options', [WebAuthnController::class, 'optionsLogin'])->name('webauthn.login.options');
Route::post('/webauthn/login/verify', [WebAuthnController::class, 'verifyLogin'])->name('webauthn.login.verify');

Route::middleware('auth')->group(function () {
    Route::get('/webauthn/register/options', [WebAuthnController::class, 'optionsRegister'])->name('webauthn.register.options');
    Route::post('/webauthn/register', [WebAuthnController::class, 'register'])->name('webauthn.register');
    Route::delete('/webauthn/credentials/{id}', [WebAuthnController::class, 'destroy'])->name('webauthn.destroy');

    // Two-Factor Authentication Settings
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('2fa.recovery-codes');

    // Recovery Email Setting
    Route::post('/recovery/email', [AccountRecoveryController::class, 'updateEmail'])->name('recovery.email.update');
});

// Two-Factor Challenge Gate
Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('2fa.verify');

// Social / OAuth Providers (Google, Apple)
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('oauth.callback');
Route::post('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('oauth.callback.post');

// Emergency Account Recovery Pipeline
Route::get('/recovery/email/verify/{id}/{hash}', [AccountRecoveryController::class, 'verifyEmail'])->name('recovery.email.verify');
Route::get('/recovery/request', [AccountRecoveryController::class, 'showRequestForm'])->name('recovery.request');
Route::post('/recovery/request', [AccountRecoveryController::class, 'sendRecoveryLink'])->name('recovery.send');
Route::get('/recovery/reset/{token}', [AccountRecoveryController::class, 'showResetForm'])->name('recovery.reset.form');
Route::post('/recovery/reset', [AccountRecoveryController::class, 'resetPassword'])->name('recovery.reset');

// Member Profile Settings
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/gps', [ProfileController::class, 'updateGps'])->name('profile.gps')->middleware('auth');

// Multilingual Locale Switcher (EN, RU, FR, IT, or auto)
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'ru', 'fr', 'it'], true)) {
        session(['locale' => $locale]);
        if (Auth::check()) {
            Auth::user()->update(['preferred_locale' => $locale]);
        }
    } elseif ($locale === 'auto') {
        session()->forget('locale');
        if (Auth::check()) {
            Auth::user()->update(['preferred_locale' => null]);
        }
    }

    return back();
})->name('locale.switch');

// Protected Admin Command Area (Restricted to Admins)
Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::post('/gps', [AdminController::class, 'updateGps'])->name('admin.gps');
    Route::post('/channels', [AdminController::class, 'storeChannel'])->name('admin.channels.store');
    Route::put('/channels/{id}', [AdminController::class, 'updateChannel'])->name('admin.channels.update');
    Route::post('/channels/{id}/toggle', [AdminController::class, 'toggleChannel'])->name('admin.channels.toggle');
    Route::post('/channels/{id}/toggle-notifications', [AdminController::class, 'toggleNotifications'])->name('admin.channels.toggle-notifications');
    Route::delete('/channels/{id}', [AdminController::class, 'destroyChannel'])->name('admin.channels.destroy');
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
});

// Private Channel Direct Access (Zero public discovery)
Route::post('/rooms/{room}/verify', [RoomController::class, 'verify'])->name('rooms.verify');

Route::prefix('c/{room}')->group(function () {
    Route::get('/', [RoomController::class, 'show'])->name('rooms.show');
    Route::post('/lock', [RoomController::class, 'lock'])->name('rooms.lock');
    Route::post('/nuke', [RoomController::class, 'nuke'])->name('rooms.nuke');
    Route::get('/member-profile', [RoomController::class, 'memberProfile'])->name('rooms.member-profile');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/translate', [MessageController::class, 'translate'])->name('messages.translate');

    Route::get('/radar', [IntelController::class, 'radar'])->name('intel.radar');
    Route::post('/gps', [IntelController::class, 'updateGps'])->name('intel.gps');
});
