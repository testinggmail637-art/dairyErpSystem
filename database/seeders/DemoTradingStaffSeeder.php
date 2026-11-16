<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\AnimalPaymentService;
use App\Services\AnimalPurchaseService;
use App\Services\AnimalSaleService;
use App\Services\SalaryRunService;
use App\Services\StaffAdvanceService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 part of DemoSeeder: three trading parties, two purchases (resale heifers + an own-herd buffalo, with a
 * first payment), a sale of one heifer with an installment received, four staff with two months of attendance, an
 * advance, last month's salary run finalized (salary expenses) and this month's as a draft.
 */
class DemoTradingStaffSeeder
{
    public function run(Farm $farm, int $userId): void
    {
        auth()->setUser(User::query()->findOrFail($userId));
        $cash = PaymentMode::query()->where('code', 'CASH')->value('id');

        $this->trading($cash);
        $this->staff($farm, $cash);
    }

    private function trading(int $cash): void
    {
        $seller = Party::create(['name' => 'Ramesh Yadav', 'phone' => '9876543210', 'village' => 'Wadgaon', 'party_type' => 'seller', 'opening_balance' => '0', 'is_active' => true]);
        $buyer = Party::create(['name' => 'Suresh Patil', 'phone' => '9822001100', 'village' => 'Loni', 'party_type' => 'buyer', 'opening_balance' => '5000', 'is_active' => true]);
        Party::create(['name' => 'Ganesh Traders', 'village' => 'Shirur', 'party_type' => 'both', 'opening_balance' => '-2000', 'is_active' => true]);

        $purchases = app(AnimalPurchaseService::class);
        $row = fn ($tag, $type, $stage, $own, $price, $age) => ['tag_no' => $tag, 'name' => null, 'animal_type' => $type, 'breed_id' => null,
            'gender' => 'female', 'age_months' => $age, 'lifecycle_stage' => $stage, 'ownership_type' => $own, 'price' => $price];

        $purchases->create(['party_id' => $seller->id, 'purchase_date' => today()->subDays(75)->toDateString(), 'transport_cost' => '1500',
            'commission' => '1000', 'other_cost' => '0', 'notes' => DemoSeeder::MARK,
            'animals' => [$row('TR-201', 'cow', 'heifer', 'resale', '35000', 26), $row('TR-202', 'cow', 'heifer', 'resale', '30000', 24)],
            'initial_payment' => '40000', 'initial_payment_mode_id' => $cash, 'initial_reference_no' => null]);
        $purchases->create(['party_id' => $seller->id, 'purchase_date' => today()->subDays(40)->toDateString(), 'transport_cost' => '2000',
            'commission' => '0', 'other_cost' => '0', 'notes' => DemoSeeder::MARK,
            'animals' => [$row('TR-203', 'buffalo', 'milking', 'own_herd', '72000', 54)],
            'initial_payment' => null, 'initial_payment_mode_id' => null, 'initial_reference_no' => null]);

        $sale = app(AnimalSaleService::class)->create(['party_id' => $buyer->id, 'sale_date' => today()->subDays(10)->toDateString(),
            'selling_costs' => '1500', 'notes' => DemoSeeder::MARK,
            'animals' => [['animal_id' => Animal::query()->where('tag_no', 'TR-201')->value('id'), 'price' => '58000']]]);

        $payments = app(AnimalPaymentService::class);
        $payments->record(['party_id' => $buyer->id, 'direction' => 'received', 'payment_date' => today()->subDays(8)->toDateString(),
            'amount' => '30000', 'payment_mode_id' => $cash, 'animal_sale_id' => $sale->id]);
        $payments->record(['party_id' => $seller->id, 'direction' => 'paid', 'payment_date' => today()->subDays(20)->toDateString(),
            'amount' => '20000', 'payment_mode_id' => $cash]);
    }

    private function staff(Farm $farm, int $cash): void
    {
        $members = collect([['Raju Pawar', 'Milker', '15000'], ['Sunil More', 'Helper', '12000'], ['Anita Shinde', 'Supervisor', '20000'], ['Kiran Jadhav', 'Driver', '14000']])
            ->map(fn ($s) => StaffMember::create(['name' => $s[0], 'designation' => $s[1], 'monthly_salary' => $s[2],
                'joining_date' => today()->subYear()->toDateString(), 'is_active' => true]));

        // last month + this month up to yesterday: mostly present, Sundays holiday, a few absences / half days / leave
        $start = today()->subMonthNoOverflow()->startOfMonth();
        $rows = [];
        foreach ($members as $i => $m) {
            for ($d = $start->copy(); $d->lt(today()); $d->addDay()) {
                $n = (int) $d->format('j') + $i;
                $status = $d->isSunday() ? 'holiday' : match (true) {
                    $n % 13 === 0 => 'absent', $n % 17 === 0 => 'half_day', $n % 19 === 0 => 'leave', default => 'present'
                };
                $rows[] = ['farm_id' => $farm->id, 'staff_member_id' => $m->id, 'attendance_date' => $d->toDateString(), 'status' => $status,
                    'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('attendances')->insert($rows);

        app(StaffAdvanceService::class)->record(['staff_member_id' => $members[0]->id, 'advance_date' => $start->copy()->addDays(9)->toDateString(),
            'amount' => '3000', 'payment_mode_id' => $cash, 'note' => 'Festival advance']);

        $runs = app(SalaryRunService::class);
        $last = $runs->create($start->format('Y-m'));
        $runs->finalize($last);
        $last->slips()->get()->each(fn ($slip) => $runs->pay($slip, $start->copy()->endOfMonth()->addDays(2)->min(today())->toDateString(), $cash));

        if (! SalaryRun::query()->whereDate('month', today()->startOfMonth())->exists()) {
            $runs->create(today()->format('Y-m')); // draft
        }
    }
}
