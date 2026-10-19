<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\AnimalStatusHistory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\CurrentFarm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Realistic demo data for one year (spec Sections 10–11) — local only, refuses to run in production.
 * Grows phase by phase. Phase 1: demo users, vendors, ~2,700 expenses (paid and credit), vendor payments.
 * Phase 2: 30 animals (milking cows and buffaloes, dry, heifers, calves with mothers, a bull).
 * Rows are bulk-inserted so the seeder finishes in seconds; amounts are built as strings (never floats).
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const MARK = 'Demo data';

    private const VENDORS = [
        'Sharma Feeds', 'Gupta Cattle Feed Store', 'Shree Krishna Fodder', 'Patel Agro Centre', 'Jai Kisan Bhusa Supply',
        'Verma Medical Store', 'Pashu Seva Veterinary Clinic', 'Yadav Transport', 'Singh Diesel Pump', 'Bharat Electricals',
        'Mahalaxmi Hardware', 'Om Sai Dairy Equipment', 'Ganesh Mineral Mixture', 'Rathore Silage', 'Kamdhenu Feeds',
        'Chaudhary Tractors', 'Balaji Stationers', 'Annapurna Traders', 'Hari Om Water Supply', 'New India Tyres',
        'Shiv Shakti Welding', 'Gopal Can Suppliers', 'Rajasthan Lab Chemicals', 'Mehta Agencies', 'Sai Ram Plumbing',
    ];

    private const PAYEES = ['Ramesh', 'Suresh Kumar', 'Mohan Lal', 'Local shop', 'Labour (daily)', 'Panchayat office', 'Kishan', 'Raju mechanic'];

    /** category => [min rupees, max rupees, entries per week] */
    private const SPEND = [
        'Feed' => [800, 25000, 18],
        'Health' => [150, 6000, 5],
        'Labour' => [400, 15000, 8],
        'Utilities' => [300, 9000, 5],
        'Transport' => [200, 5000, 6],
        'Maintenance' => [250, 12000, 2],
        'Collection Center' => [150, 4000, 3],
        'Office' => [50, 3000, 3],
        'Miscellaneous' => [50, 2000, 2],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder is for local/testing only.');
        }

        $farm = Farm::query()->where('code', 'MDF')->first() ?? throw new RuntimeException('Run `php artisan db:seed` first.');
        app(CurrentFarm::class)->set($farm);

        mt_srand(2026); // same data on every run
        $users = $this->users($farm);

        // Each phase's part runs once; re-running after a new phase adds only that phase's demo data.
        if (Vendor::query()->where('notes', self::MARK)->exists()) {
            $this->command?->warn('Phase 1 demo data (vendors, expenses) already exists — skipped.');
        } else {
            $this->expensesAndPayments($farm, $this->vendors($farm), $users);
        }

        if (Animal::query()->where('notes', self::MARK)->exists()) {
            $this->command?->warn('Phase 2 demo data (animals) already exists — skipped.');
        } else {
            $this->animals($farm);
        }

        $this->command?->info(sprintf('Demo data: %d expenses, %d vendor payments, %d animals.',
            Expense::query()->count(), VendorPayment::query()->count(), Animal::query()->count()));
    }

    /** 30 animals (spec Section 10): milking cows and buffaloes, dry, heifers, calves with their mothers, a bull. */
    private function animals(Farm $farm): void
    {
        $breeds = AnimalBreed::query()->get(['id', 'animal_type', 'name'])
            ->mapWithKeys(fn ($b) => [$b->animal_type->value.':'.$b->name => $b->id]);
        $cowNames = ['Gauri', 'Lakshmi', 'Kamdhenu', 'Ganga', 'Yamuna', 'Sundari', 'Kapila', 'Nandini', 'Radha', 'Shyama',
            'Dhauli', 'Bhuri', 'Chandni', 'Laali', 'Rani', 'Pari', 'Mohini'];
        $buffaloNames = ['Kali', 'Bhuri', 'Rajkumari', 'Sita', 'Champa', 'Moti', 'Heera', 'Basanti'];

        $rows = [ // [tag, type, breed, stage, born years ago, lactation]
            ...array_map(fn ($i) => ['C-'.(101 + $i), 'cow', ['Sahiwal', 'Gir', 'HF', 'Jersey', 'Crossbred HF', 'Crossbred Jersey'][$i % 6], 'milking', 4 + $i % 5, 1 + $i % 4], range(0, 13)),
            ...array_map(fn ($i) => ['B-'.(201 + $i), 'buffalo', ['Murrah', 'Murrah', 'Nili-Ravi', 'Mehsana'][$i % 4], 'milking', 5 + $i % 4, 1 + $i % 3], range(0, 5)),
            ['C-115', 'cow', 'Sahiwal', 'dry', 7, 4], ['C-116', 'cow', 'HF', 'dry', 6, 3], ['B-207', 'buffalo', 'Murrah', 'dry', 8, 4],
            ['C-117', 'cow', 'Gir', 'heifer', 2, 0], ['C-118', 'cow', 'Crossbred HF', 'pregnant_heifer', 2, 0], ['B-208', 'buffalo', 'Murrah', 'heifer', 2, 0],
        ];

        $ids = [];
        foreach ($rows as $n => [$tag, $type, $breed, $stage, $years, $lactation]) {
            $names = $type === 'cow' ? $cowNames : $buffaloNames;
            $ids[$tag] = Animal::query()->insertGetId([
                'farm_id' => $farm->id, 'tag_no' => $tag, 'name' => $names[$n % count($names)], 'animal_type' => $type,
                'breed_id' => $breeds[$type.':'.$breed] ?? null, 'gender' => 'female',
                'date_of_birth' => Carbon::today()->subYears($years)->subDays(mt_rand(0, 300))->toDateString(), 'dob_is_estimated' => $years > 3,
                'source' => 'opening', 'ownership_type' => 'own_herd', 'lifecycle_stage' => $stage, 'status' => 'active',
                'opening_value' => sprintf('%d.00', ($type === 'cow' ? 45 : 60) * 1000 + mt_rand(0, 30) * 1000),
                'lactation_number' => $lactation, 'notes' => self::MARK, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $calves = [['C-119', 'cow', 'female', 'C-101', 'Sahiwal'], ['C-120', 'cow', 'male', 'C-103', 'HF'], ['B-209', 'buffalo', 'female', 'B-201', 'Murrah']];
        foreach ($calves as [$tag, $type, $gender, $mother, $breed]) {
            Animal::query()->insert([
                'farm_id' => $farm->id, 'tag_no' => $tag, 'name' => null, 'animal_type' => $type, 'breed_id' => $breeds[$type.':'.$breed] ?? null,
                'gender' => $gender, 'date_of_birth' => Carbon::today()->subDays(mt_rand(20, 200))->toDateString(), 'dob_is_estimated' => false,
                'source' => 'born_on_farm', 'mother_id' => $ids[$mother], 'ownership_type' => 'own_herd', 'lifecycle_stage' => 'calf',
                'status' => 'active', 'lactation_number' => 0, 'notes' => self::MARK, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Animal::query()->insert([
            'farm_id' => $farm->id, 'tag_no' => 'BULL-1', 'name' => 'Nandi', 'animal_type' => 'cow', 'breed_id' => $breeds['cow:Gir'] ?? null,
            'gender' => 'male', 'date_of_birth' => Carbon::today()->subYears(5)->toDateString(), 'dob_is_estimated' => true,
            'source' => 'purchased', 'ownership_type' => 'own_herd', 'lifecycle_stage' => 'bull', 'status' => 'active',
            'lactation_number' => 0, 'notes' => self::MARK, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // bulk inserts skip AnimalObserver: write the starting status / stage / ownership rows here
        $history = [];
        foreach (Animal::query()->where('notes', self::MARK)->get(['id', 'status', 'lifecycle_stage', 'ownership_type', 'date_of_birth']) as $animal) {
            foreach (AnimalStatusHistory::FIELDS as $field) {
                $history[] = [
                    'farm_id' => $farm->id, 'animal_id' => $animal->id, 'field' => $field, 'old_value' => null,
                    'new_value' => $animal->getRawOriginal($field), 'changed_on' => today()->subYear()->max($animal->date_of_birth)->toDateString(),
                    'reason' => null, 'changed_by' => null, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        AnimalStatusHistory::query()->insert($history);
    }

    /** @return list<int> user ids that enter expenses */
    private function users(Farm $farm): array
    {
        $people = [
            ['Anita Accountant', 'accountant@dairy.test', '9876500001', RoleName::Accountant],
            ['Ramu Herd', 'herd@dairy.test', '9876500002', RoleName::HerdStaff],
            ['Sunil Collection', 'collection@dairy.test', '9876500003', RoleName::CollectionOperator],
            ['Vijay Delivery', 'delivery@dairy.test', '9876500004', RoleName::DeliveryBoy],
        ];

        $ids = [];
        foreach ($people as [$name, $email, $phone, $role]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'phone' => $phone, 'password' => 'password', 'is_active' => true,
            ]);
            $user->farms()->syncWithoutDetaching([$farm->id]);
            $user->assignRole($role->value);
            $ids[] = $user->id;
        }

        return array_slice($ids, 0, 2); // accountant + herd staff enter expenses
    }

    /** @return list<int> */
    private function vendors(Farm $farm): array
    {
        $now = now();
        Vendor::query()->insert(array_map(fn (string $name, int $i) => [
            'farm_id' => $farm->id,
            'name' => $name,
            'phone' => '98'.str_pad((string) (10000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
            'address' => ['Rampur', 'Sitapur', 'Barsana', 'Nandgaon', 'Govardhan'][$i % 5],
            'opening_balance' => $i % 4 === 0 ? sprintf('%d.00', mt_rand(1, 40) * 500) : '0.00',
            'is_active' => $i !== 24,
            'notes' => self::MARK,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::VENDORS, array_keys(self::VENDORS)));

        return Vendor::query()->where('notes', self::MARK)->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  list<int>  $vendorIds
     * @param  list<int>  $userIds
     */
    private function expensesAndPayments(Farm $farm, array $vendorIds, array $userIds): void
    {
        $categories = ExpenseCategory::query()->whereNull('parent_id')->with('children:id,parent_id,name')->get(['id', 'name'])->keyBy('name');
        $sections = Section::query()->pluck('id', 'code');
        $modes = PaymentMode::query()->pluck('id')->all();
        $sectionFor = fn (string $category) => match ($category) {
            'Collection Center' => $sections['COLLECTION'],
            'Transport' => mt_rand(0, 1) ? $sections['DELIVERY'] : $sections['HERD'],
            'Office', 'Miscellaneous' => $sections['OFFICE'],
            default => mt_rand(0, 9) ? $sections['HERD'] : $sections['TRADING'],
        };

        $rows = [];
        $credit = array_fill_keys($vendorIds, 0); // rupees owed per vendor, for realistic payments
        $start = Carbon::today()->subYear()->addDay();

        for ($day = $start->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            foreach (self::SPEND as $name => [$min, $max, $perWeek]) {
                $category = $categories[$name] ?? null;
                if (! $category) {
                    continue;
                }

                $count = intdiv($perWeek, 7) + (mt_rand(1, 7) <= $perWeek % 7 ? 1 : 0);
                for ($n = 0; $n < $count; $n++) {
                    $rupees = mt_rand($min, $max);
                    $isCredit = $name !== 'Labour' && mt_rand(1, 100) <= 30;
                    $vendorId = $isCredit || mt_rand(1, 3) === 1 ? $vendorIds[mt_rand(0, count($vendorIds) - 2)] : null;
                    $sub = $category->children->isNotEmpty() && mt_rand(1, 10) > 2 ? $category->children->random()->id : null;
                    $timestamp = $day->copy()->setTime(mt_rand(7, 20), mt_rand(0, 59));

                    if ($isCredit) {
                        $credit[$vendorId] += $rupees;
                    }

                    $rows[] = [
                        'farm_id' => $farm->id,
                        'expense_date' => $day->toDateString(),
                        'amount' => sprintf('%d.%02d', $rupees, mt_rand(0, 3) ? 0 : mt_rand(1, 99)),
                        'category_id' => $category->id,
                        'subcategory_id' => $sub,
                        'section_id' => $sectionFor($name),
                        'vendor_id' => $vendorId,
                        'paid_to_text' => $vendorId ? null : self::PAYEES[mt_rand(0, count(self::PAYEES) - 1)],
                        'is_credit' => $isCredit,
                        'payment_mode_id' => $isCredit ? null : $modes[mt_rand(0, min(2, count($modes) - 1))],
                        'quantity' => $name === 'Feed' && mt_rand(0, 1) ? sprintf('%d.00', mt_rand(5, 120)) : null,
                        'unit' => null,
                        'note' => mt_rand(1, 8) === 1 ? 'Bill no. '.mt_rand(100, 9999) : null,
                        'created_by' => $userIds[mt_rand(0, count($userIds) - 1)],
                        'updated_by' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            // month end: pay most vendors 60–100 % of what they are owed
            if ($day->isLastOfMonth()) {
                $payments = [];
                foreach ($credit as $vendorId => $owed) {
                    if ($owed < 500) {
                        continue;
                    }
                    $paid = intdiv($owed * mt_rand(60, 100), 100);
                    $credit[$vendorId] -= $paid;
                    $payments[] = [
                        'farm_id' => $farm->id, 'vendor_id' => $vendorId, 'payment_date' => $day->toDateString(),
                        'amount' => sprintf('%d.00', $paid), 'payment_mode_id' => $modes[mt_rand(0, count($modes) - 1)],
                        'reference_no' => 'UTR'.mt_rand(100000, 999999), 'note' => null, 'client_uuid' => null,
                        'created_by' => $userIds[0], 'updated_by' => null,
                        'created_at' => $day->copy()->setTime(18, 0), 'updated_at' => $day->copy()->setTime(18, 0),
                    ];
                }
                VendorPayment::query()->insert($payments);
            }

            if (count($rows) >= 1000) {
                Expense::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            Expense::query()->insert($rows);
        }
    }
}
