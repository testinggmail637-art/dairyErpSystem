<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled jobs (times in IST, app timezone). Production cron: * * * * * php artisan schedule:run
|--------------------------------------------------------------------------
*/

// Daily backup of the database + uploads at 02:00, keep 14 days, then check the backups are healthy.
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->onOneServer();
