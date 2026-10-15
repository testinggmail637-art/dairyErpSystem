<?php

namespace Database\Seeders;

use App\Models\Farm;
use Illuminate\Database\Seeder;

/**
 * The default farm (spec Section 11): "My Dairy Farm", code MDF. Safe to run again.
 */
class DefaultFarmSeeder extends Seeder
{
    public function run(): void
    {
        Farm::withTrashed()->firstOrCreate(
            ['code' => 'MDF'],
            ['name' => 'My Dairy Farm', 'timezone' => 'Asia/Kolkata', 'is_active' => true],
        );
    }
}
