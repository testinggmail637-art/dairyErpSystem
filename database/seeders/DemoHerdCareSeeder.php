<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\PaymentMode;
use App\Models\User;
use App\Models\VaccineType;
use App\Models\Vendor;
use App\Services\BreedingService;
use App\Services\FeedService;
use App\Services\TreatmentService;
use App\Services\VaccinationService;

/**
 * Phase 5 part of DemoSeeder: a year of feed (opening, weekly purchases through FeedService so each keeps its
 * expense, daily herd consumption bulk-inserted), ~25 treatments (a few under milk withdrawal now), FMD / HS /
 * deworming rounds for the herd, and an AI on half the milking animals (expected calvings for the Breeding screen).
 */
class DemoHerdCareSeeder
{
    /** name => [unit, reorder level, daily use, sub-category, rate, weekly purchase?] */
    private const FEED = [
        'Cattle feed' => ['kg', '300', 90, 'Cattle Feed', '28', true],
        'Dry fodder (bhusa)' => ['kg', '500', 150, 'Dry Fodder (Bhusa)', '9', true],
        'Green fodder' => ['kg', '0', 400, 'Green Fodder', '3', true],
        'Mineral mixture' => ['kg', '10', 1, 'Mineral Mixture', '110', false],
    ];

    public function run(Farm $farm, int $userId): void
    {
        auth()->setUser(User::query()->findOrFail($userId));
        $cash = PaymentMode::query()->where('code', 'CASH')->value('id');
        $herd = Animal::query()->where('notes', DemoSeeder::MARK)->where('gender', 'female')->where('status', 'active')
            ->whereIn('lifecycle_stage', ['milking', 'dry', 'heifer'])->orderBy('id')->get(['id', 'lifecycle_stage']);

        $this->feed($farm, $userId, $cash);
        $this->treatments($herd->pluck('id')->all(), $cash);
        $this->vaccinations($herd->pluck('id')->all(), $cash);
        $this->breeding($herd->where('lifecycle_stage.value', 'milking')->pluck('id')->all());
    }

    private function feed(Farm $farm, int $userId, int $cash): void
    {
        $service = app(FeedService::class);
        $vendors = Vendor::query()->where('notes', DemoSeeder::MARK)->orderBy('id')->limit(3)->pluck('id')->all();
        $feedId = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Feed')->value('id');
        $start = today()->subYear();

        foreach (self::FEED as $name => [$unit, $reorder, $daily, $sub, $rate, $weekly]) {
            $item = FeedItem::query()->create(['name' => $name, 'unit' => $unit, 'reorder_level' => $reorder, 'is_active' => true]);
            $subId = ExpenseCategory::query()->where('parent_id', $feedId)->where('name', $sub)->value('id');
            $service->create(['feed_item_id' => $item->id, 'type' => 'opening', 'transaction_date' => $start->toDateString(),
                'quantity' => (string) ($daily * 10)]);

            // purchases: weekly (or monthly) a little more than used; ~1 in 3 on credit from a demo vendor
            $step = $weekly ? 7 : 30;
            for ($day = $start->copy()->addDays($step); $day->lt(today()); $day->addDays($step)) {
                $qty = $daily * $step + mt_rand(0, $daily);
                $credit = $vendors !== [] && mt_rand(1, 3) === 1;
                $service->create(['feed_item_id' => $item->id, 'type' => 'purchase', 'transaction_date' => $day->toDateString(),
                    'quantity' => (string) $qty, 'amount' => (string) ($qty * (int) $rate), 'is_credit' => $credit,
                    'vendor_id' => $credit ? $vendors[array_rand($vendors)] : null, 'payment_mode_id' => $credit ? null : $cash,
                    'expense_subcategory_id' => $subId]);
            }

            // daily herd consumption (no expense) — never more than the opening + purchases above
            $rows = [];
            for ($day = $start->copy(); $day->lt(today()); $day->addDay()) {
                $rows[] = ['farm_id' => $farm->id, 'feed_item_id' => $item->id, 'transaction_date' => $day->toDateString(),
                    'type' => 'consumption', 'quantity' => (string) $daily, 'is_credit' => false, 'created_by' => $userId,
                    'created_at' => $day->copy()->setTime(19, 0), 'updated_at' => $day->copy()->setTime(19, 0)];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                FeedTransaction::query()->insert($chunk);
            }
        }
    }

    /** @param  list<int>  $animalIds */
    private function treatments(array $animalIds, int $cash): void
    {
        $service = app(TreatmentService::class);
        $cases = [['Mastitis', 'Ceftriaxone', 4], ['Fever', 'Meloxicam', 3], ['Foot rot', 'Oxytetracycline', 5], ['Bloat', 'Tympanil', 0], ['Indigestion', 'Rumen tonic', 0]];

        for ($i = 0; $i < 25; $i++) {
            [$diagnosis, $medicine, $withdrawal] = $cases[array_rand($cases)];
            $date = $i < 3 ? today()->subDays($i + 1) : today()->subDays(mt_rand(10, 360)); // 3 recent: under withdrawal now
            $cost = mt_rand(3, 15) * 100;
            $service->create(['animal_id' => $animalIds[array_rand($animalIds)], 'treatment_date' => $date->toDateString(),
                'diagnosis' => $diagnosis, 'medicine' => $medicine, 'vet_name' => 'Dr. Kale', 'cost' => (string) $cost,
                'milk_withdrawal_until' => $withdrawal > 0 || $i < 3 ? $date->copy()->addDays(max($withdrawal, 4))->toDateString() : null,
                'record_as_expense' => true, 'payment_mode_id' => $cash]);
        }
    }

    /** @param  list<int>  $animalIds */
    private function vaccinations(array $animalIds, int $cash): void
    {
        $service = app(VaccinationService::class);
        $types = VaccineType::query()->whereIn('name', ['FMD', 'HS', 'Deworming'])->pluck('id', 'name');
        $rounds = [['FMD', 300, 180], ['HS', 200, 365], ['Deworming', 100, 90], ['FMD', 120, 180], ['Deworming', 10, 90]]; // days ago, interval

        foreach ($rounds as [$name, $ago, $interval]) {
            $given = today()->subDays($ago);
            $service->createBulk(['vaccine_type_id' => $types[$name], 'given_on' => $given->toDateString(),
                'next_due_on' => $given->copy()->addDays($interval)->toDateString(), 'animal_ids' => $animalIds,
                'cost' => '40', 'record_as_expense' => true, 'payment_mode_id' => $cash]);
        }
    }

    /** @param  list<int>  $milkingIds */
    private function breeding(array $milkingIds): void
    {
        $service = app(BreedingService::class);

        foreach (array_slice($milkingIds, 0, intdiv(count($milkingIds), 2)) as $i => $animalId) {
            $ai = today()->subDays(40 + $i * 12);
            $service->create(['animal_id' => $animalId, 'event_type' => 'ai', 'event_date' => $ai->toDateString(),
                'sire_info' => 'HF semen #'.(1040 + $i), 'technician' => 'Mr. Shinde', 'cost' => '0', 'record_as_expense' => false]);
            $service->create(['animal_id' => $animalId, 'event_type' => 'pregnancy_check', 'event_date' => $ai->copy()->addDays(35)->toDateString(),
                'result' => $i % 4 === 3 ? 'negative' : 'positive', 'technician' => 'Mr. Shinde', 'cost' => '0', 'record_as_expense' => false]);
        }
    }
}
