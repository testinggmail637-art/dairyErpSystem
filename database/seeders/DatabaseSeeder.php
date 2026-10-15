<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seeders only (spec Section 11). Demo data lives in DemoSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultFarmSeeder::class,
            OwnerUserSeeder::class,
        ]);
    }
}
