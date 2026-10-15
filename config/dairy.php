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

];
