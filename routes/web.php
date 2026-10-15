<?php

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Farm\FarmSwitchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\FarmSettingsController;
use App\Http\Controllers\Settings\PaymentModeController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SectionController;
use App\Http\Controllers\Settings\UserController;
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
| Application (authenticated + active user, inside the current farm — EnsureUserIsActive, EnsureFarmSelected)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active', 'farm'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::view('dashboard', 'dashboard')->middleware('can:dashboard.view')->name('dashboard');

    // Farm switcher (top bar; shown only when the user has more than one farm)
    Route::post('farm/switch', FarmSwitchController::class)->name('farm.switch');

    // Own account
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications (bell)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    /*
    | Settings
    */
    Route::prefix('settings')->group(function () {
        Route::middleware('can:settings.manage')->group(function () {
            Route::get('farm', [FarmSettingsController::class, 'edit'])->name('settings.farm.edit');
            Route::put('farm', [FarmSettingsController::class, 'update'])->name('settings.farm.update');

            Route::resource('sections', SectionController::class)->except(['show', 'destroy']);
            Route::resource('payment-modes', PaymentModeController::class)->except(['show', 'destroy']);
        });

        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:users.manage');
        Route::resource('roles', RoleController::class)->except(['show'])->middleware('can:roles.manage');

        Route::get('audit-log', [AuditLogController::class, 'index'])->middleware('can:audit_logs.view')->name('audit-logs.index');
    });
});
