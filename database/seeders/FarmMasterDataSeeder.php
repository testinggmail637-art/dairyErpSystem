<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Default sections and payment modes for every farm (spec Section 6, Phase 0). Safe to run again.
 * Document sequences need no seeding: DocumentNumberService creates each row on first use.
 */
class FarmMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        Farm::query()->each(function (Farm $farm) {
            Section::seedDefaultsFor($farm);
            PaymentMode::seedDefaultsFor($farm);
        });
    }
}
