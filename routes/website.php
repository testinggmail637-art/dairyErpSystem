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
