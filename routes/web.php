<?php

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication (hand-built, no public registration, no email reset in v1)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Application (authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Own account
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications (bell)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});
