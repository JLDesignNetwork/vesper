<?php

use App\Http\Controllers\AccountRecoveryController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminEmailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BanCaseController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelHubController;
use App\Http\Controllers\ChannelInviteController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\IntelController;
use App\Http\Controllers\MemberSearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WebAuthnController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureChannelClearance;
use App\Http\Middleware\EnsureNotRestricted;
use App\Services\LanguageService;
use Illuminate\Support\Facades\Route;

// PWA Manifest
Route::get('/manifest.json', function () {
    return response(file_get_contents(public_path('manifest.json')), 200, [
        'Content-Type' => 'application/manifest+json',
    ]);
})->name('pwa.manifest');

// Public Channel Directory (browsable signed out with limited details)
Route::get('/directory', [ChannelController::class, 'directory'])->name('channels.directory');

// Public Root & Authentication Entry Point
Route::get('/', [AuthController::class, 'showLogin'])->name('portal');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:10,1');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post')->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// WebAuthn / Biometrics (Passkeys)
Route::post('/webauthn/login/options', [WebAuthnController::class, 'optionsLogin'])->name('webauthn.login.options')->middleware('throttle:20,1');
Route::post('/webauthn/login/verify', [WebAuthnController::class, 'verifyLogin'])->name('webauthn.login.verify')->middleware('throttle:10,1');

Route::middleware('auth')->group(function () {
    // Member Profile & Channels Dashboard
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/channels', function () {
        return redirect()->route('profile.show');
    })->name('channels.index');
    Route::post('/channels/enter/{room}', [ChannelHubController::class, 'enter'])->name('channels.enter');
    Route::post('/channels/invites/{roomId}/accept', [ChannelHubController::class, 'acceptInvite'])->name('channels.invites.accept');
    Route::post('/channels/invites/{roomId}/decline', [ChannelHubController::class, 'declineInvite'])->name('channels.invites.decline');
    Route::post('/channels/redeem', [ChannelHubController::class, 'redeemCode'])->name('channels.redeem')->middleware('throttle:5,1');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store')->middleware(EnsureNotRestricted::class);
    Route::post('/channels/{room}/pinless-entry', [ChannelController::class, 'updatePinlessEntry'])->name('channels.pinless-entry');
    Route::put('/channels/{room}/settings', [ChannelController::class, 'update'])->name('channels.update')->middleware(EnsureNotRestricted::class);
    Route::get('/members/search', [MemberSearchController::class, 'index'])->name('members.search')->middleware('throttle:30,1');
    Route::post('/members/{tag}/block', [ModerationController::class, 'block'])->name('members.block')->middleware('throttle:20,1');
    Route::delete('/members/{tag}/block', [ModerationController::class, 'unblock'])->name('members.unblock');
    Route::post('/members/{tag}/report', [ModerationController::class, 'report'])->name('members.report')->middleware(['throttle:5,10', EnsureNotRestricted::class]);

    // WebAuthn registration
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

    // Primary Email Verification (resend)
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->name('verification.send')->middleware('throttle:3,10');
});

// Primary Email Verification (signed link, works without an active session)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('throttle:10,1');

// Two-Factor Challenge Gate
Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('2fa.challenge');
Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('2fa.verify')->middleware('throttle:10,1');

// Social / OAuth Providers (Google, Apple)
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('oauth.callback');
Route::post('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('oauth.callback.post');

// Emergency Account Recovery Pipeline
Route::get('/recovery/email/verify/{id}/{hash}', [AccountRecoveryController::class, 'verifyEmail'])->name('recovery.email.verify');
Route::get('/recovery/request', [AccountRecoveryController::class, 'showRequestForm'])->name('recovery.request');
Route::post('/recovery/request', [AccountRecoveryController::class, 'sendRecoveryLink'])->name('recovery.send')->middleware('throttle:5,10');
Route::get('/recovery/reset/{token}', [AccountRecoveryController::class, 'showResetForm'])->name('recovery.reset.form');
Route::post('/recovery/reset', [AccountRecoveryController::class, 'resetPassword'])->name('recovery.reset')->middleware('throttle:10,1');

// Member Profile Settings
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/gps', [ProfileController::class, 'updateGps'])->name('profile.gps')->middleware('auth');

// Multilingual Locale Switcher (EN, RU, FR, IT, or auto)
Route::get('/locale/{locale}', function (string $locale) {
    if (LanguageService::isValid($locale)) {
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
    Route::get('/channels', [AdminController::class, 'channels'])->name('admin.channels.index');
    Route::get('/members', [AdminController::class, 'members'])->name('admin.members.index');
    Route::get('/operatives', fn () => redirect()->route('admin.members.index'))->name('admin.operatives.index');
    Route::get('/intel', [AdminController::class, 'intel'])->name('admin.intel.index')->middleware('can:viewGeolocation');
    Route::get('/logs', [AdminController::class, 'logs'])->name('admin.logs.index');
    Route::get('/emails', [AdminEmailController::class, 'index'])->name('admin.emails.index')->middleware('can:manageEmailTemplates');
    Route::match(['get', 'post'], '/emails/{key}/preview', [AdminEmailController::class, 'preview'])->name('admin.emails.preview')->middleware('can:manageEmailTemplates');
    Route::put('/emails/{key}', [AdminEmailController::class, 'update'])->name('admin.emails.update')->middleware('can:manageEmailTemplates');
    Route::post('/emails/{key}/auto-translate', [AdminEmailController::class, 'autoTranslate'])->name('admin.emails.auto-translate')->middleware('can:manageEmailTemplates');
    Route::post('/emails/{key}/test', [AdminEmailController::class, 'sendTest'])->name('admin.emails.test')->middleware('can:manageEmailTemplates');
    Route::post('/emails/{key}/reset', [AdminEmailController::class, 'reset'])->name('admin.emails.reset')->middleware('can:manageEmailTemplates');

    Route::post('/gps', [AdminController::class, 'updateGps'])->name('admin.gps')->middleware('can:viewGeolocation');
    Route::post('/channels', [AdminController::class, 'storeChannel'])->name('admin.channels.store')->middleware(['can:createConsoleChannels', EnsureNotRestricted::class]);
    Route::get('/channels/{id}/profile', [AdminController::class, 'channelProfile'])->name('admin.channels.profile');
    Route::put('/channels/{id}', [AdminController::class, 'updateChannel'])->name('admin.channels.update')->middleware(EnsureNotRestricted::class);
    Route::post('/channels/{id}/toggle', [AdminController::class, 'toggleChannel'])->name('admin.channels.toggle')->middleware(EnsureNotRestricted::class);
    Route::post('/channels/{id}/toggle-notifications', [AdminController::class, 'toggleNotifications'])->name('admin.channels.toggle-notifications')->middleware(EnsureNotRestricted::class);
    Route::post('/channels/{id}/invite', [AdminController::class, 'createInvite'])->name('admin.channels.invite')->middleware(EnsureNotRestricted::class);
    Route::delete('/channels/{id}', [AdminController::class, 'destroyChannel'])->name('admin.channels.destroy')->middleware(EnsureNotRestricted::class);
    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy')->middleware('can:manageMembers');
    Route::post('/users/{id}/admin-two', [AdminController::class, 'appointAdminTwo'])->name('admin.users.admin-two.appoint');
    Route::delete('/users/{id}/admin-two', [AdminController::class, 'revokeAdminTwo'])->name('admin.users.admin-two.revoke');
});

// Ban Votes (Admin I and II)
Route::middleware(['auth', 'can:voteOnBans'])->prefix('moderation/bans')->group(function () {
    Route::get('/', [BanCaseController::class, 'index'])->name('moderation.bans.index');
    Route::post('/', [BanCaseController::class, 'store'])->name('moderation.bans.store');
    Route::post('/direct', [BanCaseController::class, 'banAdminTwo'])->name('moderation.bans.direct')->middleware('can:banAdminTwo');
    Route::post('/{banCase}/vote', [BanCaseController::class, 'vote'])->name('moderation.bans.vote');
});

// Channel Invitation Links (Acceptance flow)
Route::get('/invite/{token}', [ChannelInviteController::class, 'showInvite'])->name('invites.show');
Route::post('/invite/{token}/accept', [ChannelInviteController::class, 'acceptInviteLink'])->name('invites.accept')->middleware(['auth', 'throttle:10,1']);

// Channel Access (sign-in required; guests were retired in Phase 2)
Route::middleware('auth')->group(function () {
    Route::post('/rooms/{room}/verify', [RoomController::class, 'verify'])->name('rooms.verify');
    Route::post('/rooms/{room}/passkey/options', [RoomController::class, 'passkeyOptions'])->name('rooms.passkey.options')->middleware('throttle:20,1');
    Route::post('/rooms/{room}/passkey/verify', [RoomController::class, 'passkeyVerify'])->name('rooms.passkey.verify')->middleware('throttle:10,1');

    Route::prefix('c/{room}')->group(function () {
        Route::get('/', [RoomController::class, 'show'])->name('rooms.show');
        Route::post('/lock', [RoomController::class, 'lock'])->name('rooms.lock');

        Route::middleware(EnsureChannelClearance::class)->group(function () {
            Route::post('/heartbeat', [RoomController::class, 'heartbeat'])->name('rooms.heartbeat');
            Route::post('/nuke', [RoomController::class, 'nuke'])->name('rooms.nuke')->middleware(EnsureNotRestricted::class);
            Route::get('/member-profile', [RoomController::class, 'memberProfile'])->name('rooms.member-profile');

            Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
            Route::post('/messages', [MessageController::class, 'store'])->name('messages.store')->middleware(EnsureNotRestricted::class);
            Route::post('/messages/{message}/react', [MessageController::class, 'toggleReaction'])->name('messages.react')->middleware(EnsureNotRestricted::class);
            Route::post('/messages/{message}/pin', [RoomController::class, 'togglePin'])->name('rooms.pin')->middleware(EnsureNotRestricted::class);
            Route::post('/members/{tag}/kick', [ModerationController::class, 'kick'])->name('rooms.kick')->middleware(EnsureNotRestricted::class);
            Route::delete('/members/{tag}/kick', [ModerationController::class, 'allowBack'])->name('rooms.kick.lift')->middleware(EnsureNotRestricted::class);
            Route::get('/attachments/{message}', [MessageController::class, 'streamAttachment'])->name('messages.attachment');
            Route::post('/translate', [MessageController::class, 'translate'])->name('messages.translate');
            Route::post('/translate-batch', [MessageController::class, 'translateBatch'])->name('messages.translate-batch');

            Route::get('/radar', [IntelController::class, 'radar'])->name('intel.radar');
            Route::post('/gps', [IntelController::class, 'updateGps'])->name('intel.gps');
        });
    });
});
