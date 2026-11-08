<?php

use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // GET only (Route::redirect answers every verb and would swallow the PATCH of settings/appearance below).
    foreach (['settings', 'settings/appearance'] as $uri) {
        Route::match(['get', 'head'], $uri, RedirectController::class)->defaults('destination', '/settings/profile')->defaults('status', 302);
    }

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

});

Route::patch('settings/appearance', [AppearanceController::class, 'update'])
    ->middleware('auth')
    ->name('appearance.update');

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
