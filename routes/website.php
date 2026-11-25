<?php

use App\Http\Controllers\Website\PageController;
use App\Support\Website;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website — same pages in Hindi (/…) and English (/en/…)
|--------------------------------------------------------------------------
| Route names: website.<page> (Hindi), website.en.<page> (English). Build links with Website::route().
*/
// Dummy/test site sandbox (local only): /demo → resources/views/demo/index.blade.php, /demo/{page} → demo/{page}.blade.php
if (app()->isLocal()) {
    Route::get('demo/{page?}', function (string $page = 'index') {
        abort_unless(preg_match('/^[a-z0-9-]+$/', $page) && view()->exists("demo.{$page}"), 404);

        // Cream (dark-background) Yashadhya logo from public/yashadhya-logos-dark
        return view("demo.{$page}", ['logo' => asset('yashadhya-logos-dark/yashadhya-header-logo-dark.png')]);
    })->name('demo');
}

foreach (Website::LOCALES as $locale) {
    Route::middleware("website:{$locale}")
        ->prefix($locale === Website::DEFAULT_LOCALE ? '' : $locale)
        ->name($locale === Website::DEFAULT_LOCALE ? 'website.' : "website.{$locale}.")
        ->group(function () {
            Route::get('/', [PageController::class, 'show'])->defaults('page', 'home')->name('home');

            foreach (Website::PAGES as $page) {
                Route::get($page, [PageController::class, 'show'])->defaults('page', $page)->name($page);
            }
        });
}
