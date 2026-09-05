<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IntelController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoomController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// Public Root & Authentication Entry Point
Route::get('/', [AuthController::class, 'showLogin'])->name('portal');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Member Profile Settings
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/gps', [ProfileController::class, 'updateGps'])->name('profile.gps')->middleware('auth');

// Multilingual Locale Switcher (EN, RU, FR, IT)
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['en', 'ru', 'fr', 'it'], true)) {
        session(['locale' => $locale]);
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
