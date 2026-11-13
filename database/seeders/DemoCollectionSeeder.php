<?php

namespace Database\Seeders;

use App\Enums\CycleStatus;
use App\Enums\DocumentType;
use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Models\DocumentSequence;
use App\Models\Farm;
use App\Models\MilkCollection;
use App\Models\PaymentMode;
use App\Models\RateChart;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\FarmerPaymentService;
use App\Services\RateChartService;
use App\Support\FinancialYear;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4 part of DemoSeeder: a year of the collection center. 60 farmers (40 cow, 20 buffalo), a default
 * fat + SNF chart for cow and a fat chart for buffalo, morning / evening milk on ~92 % of days (~2 % rejected),
 * ~30 recent advances, and 10-day payment cycles: older ones bulk-inserted as paid; the last three made by
 * FarmerPaymentService (paid, finalized but unpaid, draft); the current days stay open. Collections are bulk-inserted
 * (rate and amount worked out per row by RateChartService) and their stock in movements added in one INSERT … SELECT.
 */
class DemoCollectionSeeder
{
    private const NAMES = ['Ramesh Patil', 'Sunita Jadhav', 'Anil Pawar', 'Kavita Shinde', 'Mahesh More', 'Rekha Kale', 'Suresh Gaikwad',
        'Geeta Bhosale', 'Vikas Chavan', 'Asha Mane', 'Deepak Salunkhe', 'Meena Thorat', 'Ajay Deshmukh', 'Seema Kadam', 'Sanjay Nikam'];

    private const VILLAGES = ['Wadgaon', 'Shirur', 'Kesnand', 'Loni', 'Uruli', 'Theur'];

    public function run(Farm $farm, int $userId): void
    {
        auth()->setUser(User::query()->findOrFail($userId)); // finalized_by / created_by of the cycles
        $start = today()->subYear()->startOfMonth();

        $cow = RateChart::query()->create(['name' => 'Cow fat + SNF', 'milk_type' => MilkType::Cow, 'method' => RateMethod::FatSnfRate,
            'fat_rate' => '6.15', 'snf_rate' => '2.35', 'min_fat' => '3.20', 'min_snf' => '8.00', 'effective_from' => $start->toDateString(),
            'is_default' => true, 'is_active' => true]);
        $buffalo = RateChart::query()->create(['name' => 'Buffalo by fat', 'milk_type' => MilkType::Buffalo, 'method' => RateMethod::FatRate,
            'fat_rate' => '8.00', 'min_fat' => '5.50', 'effective_from' => $start->toDateString(), 'is_default' => true, 'is_active' => true]);

        $farmers = $this->farmers($farm, $userId, $start);
        $this->collections($farm, $farmers, ['cow' => $cow, 'buffalo' => $buffalo], $start, $userId);
        $this->stockIn($farm);
        $this->advances($farm, $farmers, $start, $userId);
        $this->cycles($farm, $start, $userId);
    }

    /** @return array<int, array{type: string, litres: int}> farmer id → milk type and daily litres */
    private function farmers(Farm $farm, int $userId, Carbon $start): array
    {
        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $hasBank = mt_rand(1, 100) <= 70;
            $rows[] = [
                'farm_id' => $farm->id, 'code' => sprintf('F-%03d', $i),
                'name' => self::NAMES[($i - 1) % count(self::NAMES)].($i > count(self::NAMES) ? ' '.intdiv($i - 1, count(self::NAMES)) : ''),
                'village' => self::VILLAGES[$i % count(self::VILLAGES)], 'phone' => '98'.str_pad((string) (22000000 + $i * 731), 8, '0', STR_PAD_LEFT),
                'default_milk_type' => $i <= 40 ? MilkType::Cow->value : MilkType::Buffalo->value,
                'bank_account_name' => $hasBank ? self::NAMES[($i - 1) % count(self::NAMES)] : null,
                'bank_account_no' => $hasBank ? '0'.(31000000000 + $i * 7919) : null, 'ifsc' => $hasBank ? 'SBIN000'.(1000 + $i % 7) : null,
                'opening_balance' => '0.00', 'is_active' => true, 'notes' => DemoSeeder::MARK,
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $start, 'updated_at' => $start,
            ];
        }
        DB::table('farmers')->insert($rows);

        return DB::table('farmers')->where('farm_id', $farm->id)->where('notes', DemoSeeder::MARK)->pluck('default_milk_type', 'id')
            ->map(fn ($type) => ['type' => $type, 'litres' => $type === MilkType::Cow->value ? mt_rand(6, 26) : mt_rand(4, 14)])->all();
    }

    /** @param  array<string, RateChart>  $charts */
    private function collections(Farm $farm, array $farmers, array $charts, Carbon $start, int $userId): void
    {
        $rates = app(RateChartService::class);
        $rows = [];
        $flush = function () use (&$rows) {
            DB::table('milk_collections')->insert($rows);
            $rows = [];
        };

        for ($day = $start->copy(); $day->lte(today()->subDay()); $day->addDay()) {
            $at = $day->copy()->setTime(7, 30);
            foreach ($farmers as $id => $f) {
                foreach (['morning' => 55, 'evening' => 45] as $shift => $share) {
                    if (mt_rand(1, 100) > 92) {
                        continue;
                    }
                    $qty = Money::round2(Money::div((string) ($f['litres'] * $share * mt_rand(85, 115)), '10000'));
                    $dec = fn (int $hundredths) => Money::div((string) $hundredths, '100'); // 412 → "4.12", no floats
                    [$fat, $snf] = $f['type'] === MilkType::Cow->value
                        ? [$dec(mt_rand(340, 480)), $dec(mt_rand(810, 890))]
                        : [$dec(mt_rand(580, 760)), $dec(mt_rand(880, 940))];
                    $rejected = mt_rand(1, 100) <= 2;
                    $rate = $rejected ? '0.00' : $rates->calculate($charts[$f['type']], $fat, $snf)['rate'];

                    $rows[] = [
                        'farm_id' => $farm->id, 'collection_date' => $day->toDateString(), 'shift' => $shift, 'farmer_id' => $id,
                        'milk_type' => $f['type'], 'quantity_litre' => $qty, 'fat' => $fat, 'snf' => $snf,
                        'rate_chart_id' => $rejected ? null : $charts[$f['type']]->id, 'rate_per_litre' => $rate,
                        'amount' => Money::round2(Money::mul($qty, $rate)), 'quality_status' => $rejected ? 'rejected' : 'accepted',
                        'rejection_reason' => $rejected ? 'Sour' : null,
                        'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
                    ];
                    if (count($rows) >= 1000) {
                        $flush();
                    }
                }
            }
        }
        if ($rows) {
            $flush();
        }
    }

    /** Bulk-inserted collections skip MilkStockService: their stock in movements are added in one INSERT … SELECT. */
    private function stockIn(Farm $farm): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO milk_stock_movements
                (farm_id, movement_date, shift, stock_source, milk_type, movement_type, quantity_in, quantity_out,
                 reference_type, reference_id, created_by, updated_by, created_at, updated_at)
            SELECT c.farm_id, c.collection_date, c.shift, 'collection', c.milk_type, 'collection', c.quantity_litre, 0,
                   ?, c.id, c.created_by, c.updated_by, c.created_at, c.updated_at
            FROM milk_collections c
            LEFT JOIN milk_stock_movements m ON m.reference_type = ? AND m.reference_id = c.id
            WHERE c.farm_id = ? AND c.quality_status = 'accepted' AND m.id IS NULL
            SQL, [MilkCollection::class, MilkCollection::class, $farm->id]);
    }

    private function advances(Farm $farm, array $farmers, Carbon $start, int $userId): void
    {
        $cash = PaymentMode::query()->where('code', 'CASH')->value('id');
        $ids = array_keys($farmers);
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $date = today()->subDays(mt_rand(30, 75)); // recovered by the cycles made through the service
            $rows[] = ['farm_id' => $farm->id, 'farmer_id' => $ids[mt_rand(0, count($ids) - 1)], 'advance_date' => $date->toDateString(),
                'amount' => (string) (mt_rand(5, 40) * 100).'.00', 'payment_mode_id' => $cash, 'note' => 'Cattle feed',
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $date, 'updated_at' => $date];
        }
        DB::table('farmer_advances')->insert($rows);
    }

    /**
     * Older cycles are bulk-inserted (finalized, paid in cash, no deductions — one INSERT … SELECT per cycle); the last
     * three go through FarmerPaymentService like a real user: paid, finalized-but-unpaid, draft. They recover the advances.
     */
    private function cycles(Farm $farm, Carbon $start, int $userId): void
    {
        $days = (int) $farm->setting('farmer_payment_cycle_days');
        $ranges = [];
        for ($from = $start->copy(); $from->copy()->addDays($days * 2 - 1)->lt(today()); $from->addDays($days)) {
            $ranges[] = [$from->toDateString(), $from->copy()->addDays($days - 1)->toDateString()];
        }
        $recent = array_splice($ranges, -3);

        $cash = PaymentMode::query()->where('code', 'CASH')->value('id');
        $numbers = [];
        foreach ($ranges as [$from, $to]) {
            $fy = FinancialYear::label(Carbon::parse($to));
            $numbers[$fy] = ($numbers[$fy] ?? 0) + 1;
            $at = Carbon::parse($to)->addDay()->setTime(18, 0);
            $cycleId = DB::table('farmer_payment_cycles')->insertGetId([
                'farm_id' => $farm->id, 'cycle_no' => DocumentNumberService::format(DocumentType::FarmerPayment, $fy, $numbers[$fy]),
                'from_date' => $from, 'to_date' => $to, 'status' => CycleStatus::Finalized->value, 'finalized_at' => $at, 'finalized_by' => $userId,
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
            ]);
            DB::statement(<<<'SQL'
                INSERT INTO farmer_payments
                    (farm_id, farmer_payment_cycle_id, farmer_id, total_qty, avg_fat, avg_snf, gross_amount, bonus, advance_deduction,
                     other_deduction, net_payable, status, paid_on, payment_mode_id, created_by, updated_by, created_at, updated_at)
                SELECT farm_id, ?, farmer_id, SUM(quantity_litre), ROUND(SUM(fat * quantity_litre) / SUM(quantity_litre), 2),
                       ROUND(SUM(snf * quantity_litre) / SUM(quantity_litre), 2), SUM(amount), 0, 0, 0, SUM(amount), 'paid', ?, ?, ?, ?, ?, ?
                FROM milk_collections
                WHERE farm_id = ? AND collection_date BETWEEN ? AND ? AND quality_status = 'accepted' AND farmer_payment_id IS NULL
                GROUP BY farm_id, farmer_id
                SQL, [$cycleId, $at->copy()->addDay()->toDateString(), $cash, $userId, $userId, $at, $at, $farm->id, $from, $to]);
            DB::table('milk_collections as c')
                ->join('farmer_payments as p', fn ($j) => $j->on('p.farmer_id', '=', 'c.farmer_id')->where('p.farmer_payment_cycle_id', $cycleId))
                ->where('c.farm_id', $farm->id)->whereBetween('c.collection_date', [$from, $to])
                ->where('c.quality_status', 'accepted')->whereNull('c.farmer_payment_id')
                ->update(['c.farmer_payment_id' => DB::raw('p.id')]);
        }
        foreach ($numbers as $fy => $last) {
            DocumentSequence::query()->updateOrCreate(['farm_id' => $farm->id, 'type' => DocumentType::FarmerPayment->value, 'financial_year' => $fy], ['last_number' => $last]);
        }

        $payments = app(FarmerPaymentService::class);
        $paid = $payments->createCycle(...$recent[0]);
        $payments->finalize($paid);
        $payments->markAllPaidCash($paid, $paid->to_date->copy()->addDays(2)->toDateString());
        $payments->finalize($payments->createCycle(...$recent[1]));
        $payments->createCycle(...$recent[2]);
    }
}
