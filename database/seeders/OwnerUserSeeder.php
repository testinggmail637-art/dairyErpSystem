<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the owner account from OWNER_* env values. The local defaults must never reach production.
 * (Role assignment and farm linking are added in Phase 0 · Tasks 4–5.)
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

        User::query()->firstOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => config('dairy.owner.name'),
                'phone' => config('dairy.owner.phone'),
                'password' => $password,
                'is_active' => true,
            ],
        );
    }
}
