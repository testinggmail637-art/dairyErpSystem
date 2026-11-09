<?php

use App\Http\Controllers\Account\ExportDownloadController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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
use App\Http\Controllers\Loans\LoanActionController;
use App\Http\Controllers\Loans\LoanController;
use App\Http\Controllers\Loans\LoanPaymentController;
use App\Http\Controllers\Loans\RevolvingEntryController;
use App\Http\Controllers\MilkStock\MilkStockController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\MilkReportController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\BillController;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\CustomerPaymentController;
use App\Http\Controllers\Sales\DeliveryController;
use App\Http\Controllers\Sales\DeliveryGridController;
use App\Http\Controllers\Sales\DeliveryRouteController;
use App\Http\Controllers\Sales\DeliverySheetController;
use App\Http\Controllers\Sales\ProductController;
use App\Http\Controllers\Sales\SubscriptionController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\FarmSettingsController;
use App\Http\Controllers\Settings\PaymentModeController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SectionController;
use App\Http\Controllers\Settings\UserController;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\MilkProductionRecord;
use App\Models\VendorPayment;
use App\Services\ExpenseReportService;
use App\Services\MilkReportService;
use App\Services\SalesReportService;
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
    Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::get('dashboard/finance', [DashboardController::class, 'finance'])->middleware('can:dashboard.view')->name('dashboard.finance');

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

    // own name prefix so the menu highlights "Milk Alerts", not "Milk Entry"
    Route::get('milk-production/alerts', [MilkProductionController::class, 'alerts'])
        ->middleware('can:viewAny,'.MilkProductionRecord::class)->name('milk-alerts.index');

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
    | Sales (spec Phase 3) — customers, routes and products: customers.view to see, customers.manage to add / edit
    */
    Route::get('customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::get('customers/search', [CustomerController::class, 'search'])->middleware('can:customers.view')->name('customers.search');
    Route::resource('customers', CustomerController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('can:customers.manage');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')
        ->middleware('can:customers.view')->name('customers.show');
    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger'])
        ->middleware('can:customers.view')->name('customers.ledger');
    Route::middleware('can:customers.manage')->scopeBindings()->group(function () {
        Route::post('customers/{customer}/rates', [CustomerController::class, 'storeRate'])->name('customers.rates.store');
        Route::delete('customers/{customer}/rates/{rate}', [CustomerController::class, 'destroyRate'])->name('customers.rates.destroy');
    });
    Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('can:deliveries.view')->name('deliveries.index');
    Route::post('deliveries/generate', [DeliveryController::class, 'generate'])->middleware('can:deliveries.view_all_routes')->name('deliveries.generate');
    Route::get('deliveries/sheet', [DeliverySheetController::class, 'show'])->middleware('can:deliveries.view')->name('deliveries.sheet');
    Route::middleware('can:deliveries.view_all_routes')->group(function () {
        Route::get('deliveries/grid', [DeliveryGridController::class, 'index'])->name('deliveries.grid');
        Route::post('deliveries/bulk-deliver', [DeliveryGridController::class, 'bulkDeliver'])->name('deliveries.bulk-deliver');
        Route::get('deliveries/dispatch', [DeliveryGridController::class, 'createDispatch'])->name('deliveries.dispatch.create');
        Route::post('deliveries/dispatch', [DeliveryGridController::class, 'storeDispatch'])->name('deliveries.dispatch.store');
        Route::get('deliveries/{delivery}/edit', [DeliveryGridController::class, 'edit'])->name('deliveries.edit');
        Route::put('deliveries/{delivery}', [DeliveryGridController::class, 'update'])->name('deliveries.update');
    });
    // route check + billed lock in DeliveryPolicy (via the Form Requests)
    Route::post('deliveries/{delivery}/deliver', [DeliverySheetController::class, 'deliver'])->name('deliveries.deliver');
    Route::post('deliveries/{delivery}/skip', [DeliverySheetController::class, 'skip'])->name('deliveries.skip');
    Route::post('deliveries/{delivery}/payment', [DeliverySheetController::class, 'collect'])->name('deliveries.collect');
    // Customer payments (policy: customer_payments.edit, or own same-day entry with customer_payments.create)
    Route::get('customer-payments', [CustomerPaymentController::class, 'index'])
        ->middleware('can:create,'.CustomerPayment::class)->name('customer-payments.index');
    Route::get('customer-payments/create', [CustomerPaymentController::class, 'create'])
        ->middleware(['can:create,'.CustomerPayment::class, 'can:customers.view'])->name('customer-payments.create');
    Route::post('customer-payments', [CustomerPaymentController::class, 'store'])
        ->middleware('can:create,'.CustomerPayment::class)->name('customer-payments.store');
    Route::get('customer-payments/{customer_payment}/edit', [CustomerPaymentController::class, 'edit'])
        ->middleware('can:update,customer_payment')->name('customer-payments.edit');
    Route::put('customer-payments/{customer_payment}', [CustomerPaymentController::class, 'update'])
        ->middleware('can:update,customer_payment')->name('customer-payments.update');
    Route::delete('customer-payments/{customer_payment}', [CustomerPaymentController::class, 'destroy'])
        ->middleware('can:delete,customer_payment')->name('customer-payments.destroy');

    // Bills: view / generate (+ delete draft) / finalize / cancel (spec 5.1)
    Route::get('bills/create', [BillController::class, 'create'])->middleware('can:bills.generate')->name('bills.create');
    Route::post('bills', [BillController::class, 'store'])->middleware('can:bills.generate')->name('bills.store');
    Route::delete('bills/{bill}', [BillController::class, 'destroy'])->middleware('can:bills.generate')->name('bills.destroy');
    Route::post('bills/{bill}/finalize', [BillController::class, 'finalize'])->middleware('can:bills.finalize')->name('bills.finalize');
    Route::post('bills/{bill}/cancel', [BillController::class, 'cancel'])->middleware('can:bills.cancel')->name('bills.cancel');
    Route::middleware('can:bills.view')->group(function () {
        Route::get('bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('bills/{bill}', [BillController::class, 'show'])->whereNumber('bill')->name('bills.show');
        Route::get('bills/{bill}/pdf', [BillController::class, 'pdf'])->name('bills.pdf');
    });
    // Customer adjustments (customer_adjustments.create; remove own same-day entry — CustomerAdjustmentPolicy)
    Route::scopeBindings()->group(function () {
        Route::post('customers/{customer}/adjustments', [CustomerController::class, 'storeAdjustment'])
            ->middleware('can:customer_adjustments.create')->name('customers.adjustments.store');
        Route::delete('customers/{customer}/adjustments/{adjustment}', [CustomerController::class, 'destroyAdjustment'])
            ->name('customers.adjustments.destroy');
    });
    Route::middleware('can:subscriptions.manage')->group(function () {
        Route::resource('subscriptions', SubscriptionController::class)->except('show');
        Route::scopeBindings()->group(function () {
            Route::post('subscriptions/{subscription}/pauses', [SubscriptionController::class, 'storePause'])->name('subscriptions.pauses.store');
            Route::delete('subscriptions/{subscription}/pauses/{pause}', [SubscriptionController::class, 'destroyPause'])->name('subscriptions.pauses.destroy');
        });
    });
    Route::get('delivery-routes', [DeliveryRouteController::class, 'index'])->middleware('can:customers.view')->name('delivery-routes.index');
    Route::resource('delivery-routes', DeliveryRouteController::class)
        ->only(['create', 'store', 'edit', 'update'])
        ->middleware('can:customers.manage');
    Route::get('products', [ProductController::class, 'index'])->middleware('can:customers.view')->name('products.index');
    Route::resource('products', ProductController::class)
        ->only(['create', 'store', 'edit', 'update'])
        ->middleware('can:customers.manage');

    /*
    | Reports
    */
    Route::middleware('can:reports.view')->prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('expenses/{report}', [ExpenseReportController::class, 'show'])
            ->whereIn('report', ExpenseReportService::REPORTS)->name('reports.expenses');
        Route::get('milk/{report}', [MilkReportController::class, 'show'])
            ->whereIn('report', MilkReportService::REPORTS)->name('reports.milk');
        Route::get('sales/{report}', [SalesReportController::class, 'show'])
            ->whereIn('report', SalesReportService::REPORTS)->name('reports.sales');
    });

    // Download a queued (large) export file made for the logged-in user
    Route::get('exports/{file}', ExportDownloadController::class)->name('exports.download');

    // Loans & overdraft (spec Phase 3A): loans.view to see, loans.manage to add / edit / delete
    Route::middleware('can:loans.manage')->group(function () {
        Route::get('loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
        Route::get('loans/{loan}/edit', [LoanController::class, 'edit'])->name('loans.edit');
        Route::put('loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
        Route::delete('loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
    });
    // Loan payments / charges (policy: loan_payments.edit, or own same-day entry with loan_payments.create; latest only)
    Route::middleware('can:loan_payments.create')->group(function () {
        Route::get('loans/{loan}/payments/create', [LoanPaymentController::class, 'create'])->name('loans.payments.create');
        Route::post('loans/{loan}/payments', [LoanPaymentController::class, 'store'])->name('loans.payments.store');
        Route::get('loans/{loan}/payments/split', [LoanPaymentController::class, 'split'])->name('loans.payments.split');
        Route::get('loans/{loan}/entries/create', [RevolvingEntryController::class, 'create'])->name('loans.entries.create');
        Route::post('loans/{loan}/entries', [RevolvingEntryController::class, 'store'])->name('loans.entries.store');
        Route::get('loans/{loan}/prepayment', [LoanActionController::class, 'prepaymentForm'])->name('loans.prepayment.create');
        Route::post('loans/{loan}/prepayment', [LoanActionController::class, 'prepay'])->name('loans.prepayment.store');
    });
    Route::middleware('can:loans.manage')->group(function () {
        Route::get('loans/{loan}/rate', [LoanActionController::class, 'rateForm'])->name('loans.rate.edit');
        Route::post('loans/{loan}/rate', [LoanActionController::class, 'changeRate'])->name('loans.rate.update');
        Route::post('loans/{loan}/close', [LoanActionController::class, 'close'])->name('loans.close');
        Route::post('loans/{loan}/reopen', [LoanActionController::class, 'reopen'])->name('loans.reopen'); // owner only (request)
    });
    Route::get('loan-transactions/{loan_transaction}/edit', [LoanPaymentController::class, 'edit'])
        ->middleware('can:update,loan_transaction')->name('loan-transactions.edit');
    Route::put('loan-transactions/{loan_transaction}', [LoanPaymentController::class, 'update'])->name('loan-transactions.update');
    Route::get('loan-transactions/{loan_transaction}/entry', [RevolvingEntryController::class, 'edit'])
        ->middleware('can:update,loan_transaction')->name('loan-transactions.entry.edit');
    Route::put('loan-transactions/{loan_transaction}/entry', [RevolvingEntryController::class, 'update'])->name('loan-transactions.entry.update');
    Route::delete('loan-transactions/{loan_transaction}', [LoanPaymentController::class, 'destroy'])->name('loan-transactions.destroy');
    Route::middleware('can:loans.view')->group(function () {
        Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('loans/{loan}', [LoanController::class, 'show'])->whereNumber('loan')->name('loans.show');
    });

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
