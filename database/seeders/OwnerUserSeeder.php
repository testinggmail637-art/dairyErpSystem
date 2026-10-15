<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the owner account from OWNER_* env values. The local defaults must never reach production.
 * Linked to every farm (run DefaultFarmSeeder first). Role assignment is added in Phase 0 · Task 5.
 */
class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('dairy.owner.email');
        $password = config('dairy.owner.password');

        if (app()->isProduction() && ($password === 'password' || $email === 'owner@dairy.test')) {
            throw new RuntimeException('Set OWNER_EMAIL and OWNER_PASSWORD in .env before seeding production.');
        }

        $owner = User::query()->firstOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => config('dairy.owner.name'),
                'phone' => config('dairy.owner.phone'),
                'password' => $password,
                'is_active' => true,
            ],
        );

        // The owner works in every farm.
        $owner->farms()->syncWithoutDetaching(Farm::query()->pluck('id'));
    }
}
