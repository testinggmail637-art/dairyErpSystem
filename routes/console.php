<?php

use App\Support\Exporter;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Scheduled jobs (times in IST, app timezone). Production cron: * * * * * php artisan schedule:run
|--------------------------------------------------------------------------
*/

// Daily backup of the database + uploads at 02:00, keep 14 days, then check the backups are healthy.
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();

// Queued export files (storage/app/private/exports/{user}/) are kept 3 days.
Schedule::call(function () {
    $disk = Storage::disk(Exporter::DISK);
    foreach ($disk->allFiles('exports') as $file) {
        if ($disk->lastModified($file) < now()->subDays(3)->getTimestamp()) {
            $disk->delete($file);
        }
    }
})->dailyAt('03:30')->name('exports:prune')->onOneServer();
