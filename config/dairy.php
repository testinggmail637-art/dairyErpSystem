<?php

return [

    /*
    | Owner account created by `php artisan db:seed` (OwnerUserSeeder).
    | The defaults are for local development only; the seeder refuses them in production.
    */
    'owner' => [
        'name' => env('OWNER_NAME', 'Owner'),
        'email' => env('OWNER_EMAIL', 'owner@dairy.test'),
        'phone' => env('OWNER_PHONE', '9999999999'),
        'password' => env('OWNER_PASSWORD', 'password'),
    ],

    /*
    | Excel exports with more rows than this are built by a queued job and announced in the bell (App\Support\Exporter).
    */
    'exports' => [
        'queue_above' => (int) env('EXPORT_QUEUE_ABOVE', 5000),
    ],

    /*
    | An imported Excel upload can be reverted (its expenses deleted) only within this many days of the import (owner's rule).
    */
    'imports' => [
        'revert_days' => (int) env('IMPORT_REVERT_DAYS', 7),
    ],

];
