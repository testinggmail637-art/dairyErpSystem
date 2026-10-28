<?php

use App\Http\Controllers\Account\ExportDownloadController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Expenses\ExpenseCategoryController;
use App\Http\Controllers\Expenses\ExpenseController;
use App\Http\Controllers\Expenses\ExpenseImportController;
use App\Http\Controllers\Expenses\VendorController;
use App\Http\Controllers\Expenses\VendorPaymentController;
use App\Http\Controllers\Farm\FarmSwitchController;
use App\Http\Controllers\Herd\AnimalBreedController;
use App\Http\Controllers\Herd\AnimalController;
use App\Http\Controllers\Herd\MilkProductionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MilkStock\MilkStockController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\MilkReportController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\FarmSettingsController;
use App\Http\Controllers\Settings\PaymentModeController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SectionController;
use App\Http\Controllers\Settings\UserController;
use App\Models\Expense;
use App\Models\MilkProductionRecord;
use App\Models\VendorPayment;
use App\Services\ExpenseReportService;
use App\Services\MilkReportService;
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
    | Expenses (Phase 1)
    */
    Route::prefix('expenses')->group(function () {
        Route::get('categories', [ExpenseCategoryController::class, 'index'])
            ->middleware('can:expenses.view')->name('expense-categories.index');
        Route::resource('categories', ExpenseCategoryController::class)
            ->except(['index', 'show'])
            ->parameters(['categories' => 'expense_category'])
            ->names('expense-categories')
            ->middleware('can:expenses.edit');
        Route::get('categories/{expense_category}/children', [ExpenseCategoryController::class, 'children'])
            ->name('expense-categories.children'); // expenses.view or expenses.create, checked in the controller

        Route::middleware('can:expenses.import')->prefix('import')->name('expense-imports.')->group(function () {
            Route::get('/', [ExpenseImportController::class, 'index'])->name('index');
            Route::get('template', [ExpenseImportController::class, 'template'])->name('template');
            Route::post('/', [ExpenseImportController::class, 'store'])->name('store');
            Route::get('{batch}', [ExpenseImportController::class, 'show'])->whereNumber('batch')->name('show');
            Route::post('{batch}/import', [ExpenseImportController::class, 'import'])->whereNumber('batch')->name('import');
            Route::get('{batch}/errors', [ExpenseImportController::class, 'errors'])->whereNumber('batch')->name('errors');
            Route::post('{batch}/cancel', [ExpenseImportController::class, 'cancel'])->whereNumber('batch')->name('cancel');
            Route::post('{batch}/revert', [ExpenseImportController::class, 'revert'])->whereNumber('batch')->name('revert');
        });

        Route::get('/', [ExpenseController::class, 'index'])->middleware('can:viewAny,'.Expense::class)->name('expenses.index');
        Route::get('export', [ExpenseController::class, 'export'])->middleware('can:expenses.view')->name('expenses.export');
        Route::get('create', [ExpenseController::class, 'create'])->middleware('can:expenses.create')->name('expenses.create');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('can:expenses.create')->name('expenses.store');
        Route::whereNumber('expense')->group(function () {
            Route::get('{expense}', [ExpenseController::class, 'show'])->middleware('can:view,expense')->name('expenses.show');
            Route::get('{expense}/receipt', [ExpenseController::class, 'receipt'])->middleware('can:view,expense')->name('expenses.receipt');
            Route::get('{expense}/edit', [ExpenseController::class, 'edit'])->middleware('can:update,expense')->name('expenses.edit');
            Route::put('{expense}', [ExpenseController::class, 'update'])->middleware('can:update,expense')->name('expenses.update');
            Route::delete('{expense}', [ExpenseController::class, 'destroy'])->middleware('can:delete,expense')->name('expenses.destroy');
        });
    });

    /*
    | Herd (Phase 2)
    */
    Route::prefix('animals')->group(function () {
        Route::get('breeds', [AnimalBreedController::class, 'index'])->middleware('can:animals.view')->name('animal-breeds.index');
        Route::get('breeds/by-type/{type}', [AnimalBreedController::class, 'byType'])
            ->middleware('can:animals.view')->name('animal-breeds.by-type');
        Route::resource('breeds', AnimalBreedController::class)
            ->only(['create', 'store', 'edit', 'update'])
            ->parameters(['breeds' => 'animal_breed'])
            ->names('animal-breeds')
            ->middleware('can:animals.manage');

        Route::get('/', [AnimalController::class, 'index'])->middleware('can:animals.view')->name('animals.index');
        Route::get('search', [AnimalController::class, 'search'])->name('animals.search'); // animals.view or expense rights, checked in the controller
        Route::get('create', [AnimalController::class, 'create'])->middleware('can:animals.manage')->name('animals.create');
        Route::post('/', [AnimalController::class, 'store'])->middleware('can:animals.manage')->name('animals.store');
        Route::whereNumber('animal')->group(function () {
            Route::get('{animal}', [AnimalController::class, 'show'])->middleware('can:animals.view')->name('animals.show');
            Route::get('{animal}/edit', [AnimalController::class, 'edit'])->middleware('can:animals.manage')->name('animals.edit');
            Route::put('{animal}', [AnimalController::class, 'update'])->middleware('can:animals.manage')->name('animals.update');
            Route::delete('{animal}', [AnimalController::class, 'destroy'])->middleware('can:animals.manage')->name('animals.destroy');
            Route::post('{animal}/change', [AnimalController::class, 'change'])->middleware('can:animals.manage')->name('animals.change');
        });
    });

    Route::prefix('milk-production')->name('milk-production.')->group(function () {
        Route::get('/', [MilkProductionController::class, 'index'])->middleware('can:viewAny,'.MilkProductionRecord::class)->name('index');
        Route::post('bulk', [MilkProductionController::class, 'storeBulk'])->name('bulk'); // create, checked in the request
        Route::get('entries', [MilkProductionController::class, 'entries'])->middleware('can:viewAny,'.MilkProductionRecord::class)->name('entries');
        Route::get('create', [MilkProductionController::class, 'create'])->middleware('can:create,'.MilkProductionRecord::class)->name('create');
        Route::post('/', [MilkProductionController::class, 'store'])->middleware('can:create,'.MilkProductionRecord::class)->name('store');
        Route::whereNumber('record')->group(function () {
            Route::get('{record}/edit', [MilkProductionController::class, 'edit'])->middleware('can:update,record')->name('edit');
            Route::put('{record}', [MilkProductionController::class, 'update'])->middleware('can:update,record')->name('update');
            Route::delete('{record}', [MilkProductionController::class, 'destroy'])->middleware('can:delete,record')->name('destroy');
        });
    });

    /*
    | Milk stock (spec 7.4) — usage needs milk_stock.view, adjustments milk_stock.adjust (also checked in the request)
    */
    Route::prefix('milk-stock')->name('milk-stock.')->group(function () {
        Route::middleware('can:milk_stock.view')->group(function () {
            Route::get('/', [MilkStockController::class, 'index'])->name('index');
            Route::get('ledger', [MilkStockController::class, 'ledger'])->name('ledger');
            Route::get('usage/create', [MilkStockController::class, 'createUsage'])->name('usage.create');
            Route::post('usage', [MilkStockController::class, 'store'])->name('usage.store');
        });
        Route::middleware('can:milk_stock.adjust')->group(function () {
            Route::get('adjustments/create', [MilkStockController::class, 'createAdjustment'])->name('adjustments.create');
            Route::post('adjustments', [MilkStockController::class, 'store'])->name('adjustments.store');
        });
    });

    /*
    | Reports
    */
    Route::middleware('can:reports.view')->prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('expenses/{report}', [ExpenseReportController::class, 'show'])
            ->whereIn('report', ExpenseReportService::REPORTS)->name('reports.expenses');
        Route::get('milk/{report}', [MilkReportController::class, 'show'])
            ->whereIn('report', MilkReportService::REPORTS)->name('reports.milk');
    });

    // Download a queued (large) export file made for the logged-in user
    Route::get('exports/{file}', ExportDownloadController::class)->name('exports.download');

    Route::get('vendors', [VendorController::class, 'index'])->middleware('can:vendors.view')->name('vendors.index');
    Route::get('vendors/search', [VendorController::class, 'search'])->middleware('can:vendors.view')->name('vendors.search');
    Route::post('vendors/quick', [VendorController::class, 'quickStore'])->middleware('can:vendors.manage')->name('vendors.quick-store');
    Route::resource('vendors', VendorController::class)->except(['index', 'show'])->middleware('can:vendors.manage');
    Route::get('vendors/{vendor}', [VendorController::class, 'show'])->whereNumber('vendor')
        ->middleware('can:vendors.view')->name('vendors.show'); // ledger

    // Payments to vendors (policy: vendor_payments.edit, or own same-day entry with vendor_payments.create)
    Route::get('vendor-payments/create', [VendorPaymentController::class, 'create'])
        ->middleware('can:create,'.VendorPayment::class)->name('vendor-payments.create');
    Route::post('vendor-payments', [VendorPaymentController::class, 'store'])
        ->middleware('can:create,'.VendorPayment::class)->name('vendor-payments.store');
    Route::whereNumber('vendor_payment')->group(function () {
        Route::get('vendor-payments/{vendor_payment}/edit', [VendorPaymentController::class, 'edit'])
            ->middleware('can:update,vendor_payment')->name('vendor-payments.edit');
        Route::put('vendor-payments/{vendor_payment}', [VendorPaymentController::class, 'update'])
            ->middleware('can:update,vendor_payment')->name('vendor-payments.update');
        Route::delete('vendor-payments/{vendor_payment}', [VendorPaymentController::class, 'destroy'])
            ->middleware('can:delete,vendor_payment')->name('vendor-payments.destroy');
    });

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

        Route::middleware('can:appearance.manage')->prefix('appearance')->name('settings.appearance.')->group(function () {
            Route::get('/', [AppearanceController::class, 'edit'])->name('edit');
            Route::put('/', [AppearanceController::class, 'update'])->name('update');
            Route::post('reset', [AppearanceController::class, 'reset'])->name('reset');
            Route::get('sample-pdf', [AppearanceController::class, 'samplePdf'])->name('sample-pdf');
        });
    });
});
