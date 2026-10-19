<?php

namespace Database\Seeders;

use App\Models\AnimalBreed;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Default sections, payment modes, expense categories and animal breeds for every farm (spec Section 6). Safe to run again.
 * Document sequences need no seeding: DocumentNumberService creates each row on first use.
 */
class FarmMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        Farm::query()->each(function (Farm $farm) {
            Section::seedDefaultsFor($farm);
            PaymentMode::seedDefaultsFor($farm);
            ExpenseCategory::seedDefaultsFor($farm);
            AnimalBreed::seedDefaultsFor($farm);
        });
    }
}
