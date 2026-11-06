<?php

namespace Database\Seeders;

use App\Enums\BillStatus;
use App\Enums\DocumentType;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\DocumentSequence;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Services\PaymentAllocationService;
use App\Support\FinancialYear;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 part of DemoSeeder: a year of milk sales sized to the demo herd (~100 L cow + ~30 L buffalo a day).
 * 4 routes, 85 home customers (15 % with an own rate), 2 hotels, 1 dairy plant (weekly dispatch with readings),
 * subscriptions with a few pauses / stops, ~40,000 deliveries (+ their stock out movements), finalized monthly
 * bills with numbers and balances, payments (most in full, some part) allocated oldest bill first. The current
 * month is left unbilled. Everything is bulk-inserted; money is built as strings.
 */
class DemoSalesSeeder
{
    private const FIRST = ['Ramesh', 'Sunita', 'Anil', 'Priya', 'Mahesh', 'Kavita', 'Rajesh', 'Pooja', 'Suresh', 'Neha', 'Vikas', 'Asha',
        'Deepak', 'Rekha', 'Manoj', 'Geeta', 'Ajay', 'Seema', 'Sanjay', 'Meena'];

    private const LAST = ['Sharma', 'Patil', 'Verma', 'Yadav', 'Gupta', 'Joshi', 'Singh', 'Kulkarni', 'Mehta', 'Chauhan'];

    private const AREAS = ['Shivaji Nagar', 'Gandhi Chowk', 'Station Road', 'Ram Nagar', 'Market Yard', 'Laxmi Colony'];

    public function run(Farm $farm, int $deliveryUserId, int $accountantId): void
    {
        $start = today()->subYear()->startOfMonth();
        $cow = Product::query()->where('code', 'COW')->firstOrFail();
        $buffalo = Product::query()->where('code', 'BUF')->firstOrFail();
        $routes = collect(['Ward 1 – Station Road', 'Ward 2 – Market', 'Ward 3 – Colony', 'Ward 4 – Highway'])
            ->map(fn ($name, $i) => DeliveryRoute::query()->create(['name' => $name, 'sort_order' => $i + 1, 'delivery_user_id' => $deliveryUserId, 'is_active' => true]))
            ->values();

        $customers = $this->customers($farm, $routes->pluck('id')->all(), $start);
        $rates = $this->rates($farm, $customers, $cow, $buffalo, $start);
        $subs = $this->subscriptions($farm, $customers, $cow, $buffalo, $start);
        $this->deliveries($farm, $subs, $rates, $customers, $cow, $buffalo, $start, $deliveryUserId);
        $this->stockOut($farm);
        $this->billsAndPayments($farm, $customers, $start, $accountantId);
    }

    /** @return array<int, array{id: int, route_id: ?int, kind: string, opening: string}> */
    private function customers(Farm $farm, array $routeIds, Carbon $start): array
    {
        $rows = [];
        $now = now();

        for ($i = 1; $i <= 88; $i++) {
            $kind = match (true) {
                $i <= 85 => 'home', $i <= 87 => 'hotel', default => 'plant'
            };
            $route = $kind === 'home' ? $routeIds[($i - 1) % count($routeIds)] : null;
            $rows[] = [
                'farm_id' => $farm->id, 'code' => sprintf('C-%04d', $i),
                'name' => match ($kind) {
                    'home' => self::FIRST[$i % 20].' '.self::LAST[intdiv($i, 20) % 10 + ($i % 3)],
                    'hotel' => $i === 86 ? 'Hotel Sai Palace' : 'Annapurna Sweets',
                    default => 'District Dairy Plant',
                },
                'customer_type' => $kind === 'home' ? 'b2c' : 'b2b',
                'b2b_category' => match ($kind) {
                    'hotel' => $i === 86 ? 'hotel' : 'sweet_shop', 'plant' => 'dairy_plant', default => null
                },
                'phone' => '98'.str_pad((string) (76500000 + $i * 37), 8, '0', STR_PAD_LEFT),
                'address' => $kind === 'home' ? 'House '.mt_rand(1, 250) : 'MIDC Road',
                'area' => self::AREAS[$i % count(self::AREAS)],
                'route_id' => $route, 'route_sequence' => $route ? intdiv($i - 1, count($routeIds)) + 1 : null,
                'opening_balance' => mt_rand(1, 5) === 1 ? mt_rand(100, 900).'.00' : '0.00',
                'credit_limit' => null, 'is_active' => true, 'notes' => DemoSeeder::MARK,
                'created_at' => $start, 'updated_at' => $now,
            ];
        }

        Customer::query()->insert($rows);

        return Customer::query()->where('notes', DemoSeeder::MARK)->orderBy('id')->get(['id', 'route_id', 'b2b_category', 'opening_balance'])
            ->map(fn ($c) => ['id' => $c->id, 'route_id' => $c->route_id, 'opening' => $c->opening_balance,
                'kind' => match ($c->b2b_category?->value) {
                    null => 'home', 'dairy_plant' => 'plant', default => 'hotel'
                }])
            ->keyBy('id')->all();
    }

    /** @return array<string, string> "customerId:productId" => own rate */
    private function rates(Farm $farm, array $customers, Product $cow, Product $buffalo, Carbon $start): array
    {
        $rates = [];

        foreach ($customers as $id => $c) {
            $own = match ($c['kind']) {
                'hotel' => [$cow->id => '55.00'],
                'plant' => [$buffalo->id => '52.00'],
                default => mt_rand(1, 100) <= 15 ? [$cow->id => '58.00', $buffalo->id => '68.00'] : [],
            };
            foreach ($own as $productId => $rate) {
                $rates["{$id}:{$productId}"] = $rate;
            }
        }

        DB::table('customer_rates')->insert(collect($rates)->map(function ($rate, $key) use ($farm, $start) {
            [$customerId, $productId] = explode(':', $key);

            return ['farm_id' => $farm->id, 'customer_id' => $customerId, 'product_id' => $productId, 'rate' => $rate,
                'effective_from' => $start->toDateString(), 'created_at' => $start, 'updated_at' => $start];
        })->values()->all());

        return $rates;
    }

    /** @return list<array{id: int, customer_id: int, product_id: int, shift: string, qty: string, start: string, end: ?string, paused: ?array}> */
    private function subscriptions(Farm $farm, array $customers, Product $cow, Product $buffalo, Carbon $start): array
    {
        $plan = [];
        $homeNo = 0;

        foreach ($customers as $id => $c) {
            if ($c['kind'] === 'plant') {
                continue;
            }
            if ($c['kind'] === 'hotel') {
                $plan[] = [$id, $cow->id, 'morning', '5.00'];

                continue;
            }
            $product = ++$homeNo <= 70 ? $cow->id : $buffalo->id;
            $plan[] = [$id, $product, 'morning', ['0.50', '1.00', '1.00', '1.50', '2.00'][mt_rand(0, 4)]];
            if (mt_rand(1, 10) <= 3) {
                $plan[] = [$id, $product, 'evening', ['0.50', '1.00'][mt_rand(0, 1)]];
            }
        }

        $subs = [];
        foreach ($plan as [$customerId, $productId, $shift, $qty]) {
            $stopped = mt_rand(1, 20) === 1;
            $end = $stopped ? today()->subMonths(3)->toDateString() : null;
            $id = DB::table('subscriptions')->insertGetId([
                'farm_id' => $farm->id, 'customer_id' => $customerId, 'product_id' => $productId, 'shift' => $shift, 'quantity' => $qty,
                'stock_source' => 'own_farm', 'start_date' => $start->toDateString(), 'end_date' => $end,
                'status' => $stopped ? 'stopped' : 'active', 'notes' => null, 'created_at' => $start, 'updated_at' => $start,
            ]);

            $paused = null;
            if (mt_rand(1, 10) === 1) {
                $from = $start->copy()->addDays(mt_rand(30, 300));
                $paused = [$from->toDateString(), $from->copy()->addDays(mt_rand(2, 5))->toDateString()];
                DB::table('subscription_pauses')->insert(['farm_id' => $farm->id, 'subscription_id' => $id, 'from_date' => $paused[0],
                    'to_date' => $paused[1], 'reason' => 'Out of town', 'created_at' => $start, 'updated_at' => $start]);
            }

            $subs[] = ['id' => $id, 'customer_id' => $customerId, 'product_id' => $productId, 'shift' => $shift, 'qty' => $qty,
                'start' => $start->toDateString(), 'end' => $end, 'paused' => $paused];
        }

        return $subs;
    }

    private function deliveries(Farm $farm, array $subs, array $rates, array $customers, Product $cow, Product $buffalo, Carbon $start, int $userId): void
    {
        $defaults = [$cow->id => $cow->default_rate, $buffalo->id => $buffalo->default_rate];
        $plantId = collect($customers)->firstWhere('kind', 'plant')['id'];
        $rows = [];
        $flush = function () use (&$rows) {
            DB::table('deliveries')->insert($rows);
            $rows = [];
        };

        for ($day = $start->copy(); $day->lt(today()); $day->addDay()) {
            $date = $day->toDateString();
            $at = $day->copy()->setTime(7, 15);

            foreach ($subs as $s) {
                if ($date < $s['start'] || ($s['end'] && $date > $s['end']) || ($s['paused'] && $date >= $s['paused'][0] && $date <= $s['paused'][1])) {
                    continue;
                }
                $rate = $rates["{$s['customer_id']}:{$s['product_id']}"] ?? $defaults[$s['product_id']];
                $skipped = mt_rand(1, 100) <= 3;
                $qty = $skipped ? '0.00' : (mt_rand(1, 20) === 1 ? Money::add($s['qty'], '0.50') : $s['qty']);
                $rows[] = $this->row($farm, $date, $s['shift'], $s['customer_id'], $s['product_id'], $customers[$s['customer_id']]['route_id'],
                    $s['id'], $s['qty'], $qty, $rate, $skipped ? 'skipped' : 'delivered', $userId, $at) + ['skip_reason' => $skipped ? 'House locked' : null];
            }

            if ($day->dayOfWeek === Carbon::SATURDAY) { // weekly tanker to the dairy plant, with its readings
                $rows[] = $this->row($farm, $date, 'morning', $plantId, $buffalo->id, null, null, '0.00', '60.00', '52.00', 'delivered', $userId, $at)
                    + ['skip_reason' => null, 'fat' => '6.80', 'snf' => '9.10', 'buyer_measured_qty' => (59 + mt_rand(0, 15) / 10).'0',
                        'buyer_fat' => '6.'.mt_rand(5, 8).'0', 'buyer_snf' => '9.'.mt_rand(0, 1).'0'];
            }

            if (count($rows) >= 1000) {
                $flush();
            }
        }

        if ($rows !== []) {
            $flush();
        }
    }

    /** @return array<string, mixed> */
    private function row(Farm $farm, string $date, string $shift, int $customerId, int $productId, ?int $routeId, ?int $subId, string $planned, string $qty, string $rate, string $status, int $userId, Carbon $at): array
    {
        return [
            'farm_id' => $farm->id, 'delivery_date' => $date, 'shift' => $shift, 'customer_id' => $customerId, 'product_id' => $productId,
            'route_id' => $routeId, 'subscription_id' => $subId, 'stock_source' => 'own_farm', 'planned_qty' => $planned, 'delivered_qty' => $qty,
            'rate' => $rate, 'amount' => Money::round2(Money::mul($qty, $rate)), 'status' => $status, 'delivered_by' => $userId, 'delivered_at' => $at,
            'fat' => null, 'snf' => null, 'buyer_measured_qty' => null, 'buyer_fat' => null, 'buyer_snf' => null,
            'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $at, 'updated_at' => $at,
        ];
    }

    /** Bulk-inserted deliveries skip MilkStockService: their stock out movements are added in one INSERT … SELECT. */
    private function stockOut(Farm $farm): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO milk_stock_movements
                (farm_id, movement_date, shift, stock_source, milk_type, movement_type, quantity_in, quantity_out,
                 reference_type, reference_id, created_by, updated_by, created_at, updated_at)
            SELECT d.farm_id, d.delivery_date, d.shift, d.stock_source, p.milk_type, 'delivery', 0, d.delivered_qty,
                   ?, d.id, d.created_by, d.updated_by, d.created_at, d.updated_at
            FROM deliveries d
            JOIN products p ON p.id = d.product_id
            LEFT JOIN milk_stock_movements m ON m.reference_type = ? AND m.reference_id = d.id
            WHERE d.farm_id = ? AND d.status = 'delivered' AND d.delivered_qty > 0 AND m.id IS NULL
            SQL, [Delivery::class, Delivery::class, $farm->id]);
    }

    /**
     * One finalized bill per customer per finished month (dated the month's last day, numbered in order), the spec's
     * balance snapshots worked out per customer in date order, then payments a few days later (85 % in full, the
     * rest part), allocated oldest bill first. The current month stays unbilled.
     */
    private function billsAndPayments(Farm $farm, array $customers, Carbon $start, int $accountantId): void
    {
        $months = [];
        for ($m = $start->copy(); $m->lt(today()->startOfMonth()); $m->addMonthNoOverflow()) {
            $months[] = [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()];
        }

        $totals = DB::table('deliveries')->where('farm_id', $farm->id)->where('status', 'delivered')
            ->where('delivery_date', '<', today()->startOfMonth()->toDateString())
            ->groupBy('customer_id', DB::raw("DATE_FORMAT(delivery_date, '%Y-%m')"))
            ->selectRaw("customer_id, DATE_FORMAT(delivery_date, '%Y-%m') AS ym, SUM(amount) AS total")
            ->get()->keyBy(fn ($r) => $r->customer_id.'|'.$r->ym);

        $modes = PaymentMode::query()->active()->pluck('id')->all();
        $numbers = [];
        $dueDays = (int) $farm->setting('bill_due_days');

        foreach ($customers as $id => $c) {
            $balance = $c['opening'];
            $paymentsByDate = [];

            foreach ($months as [$from, $to]) {
                $total = Money::round2((string) ($totals[$id.'|'.substr($from, 0, 7)]->total ?? '0'));
                if (Money::isZero($total)) {
                    continue;
                }

                $fy = FinancialYear::label(Carbon::parse($to));
                $numbers[$fy] = ($numbers[$fy] ?? 0) + 1;
                $paid = collect($paymentsByDate)->filter(fn ($amt, $date) => $date >= $from && $date <= $to)->reduce(fn ($s, $a) => Money::add($s, $a), '0.00');
                $closing = Money::sub(Money::add($balance, $total), $paid);

                $billId = DB::table('bills')->insertGetId([
                    'farm_id' => $farm->id, 'bill_no' => DocumentNumberService::format(DocumentType::Bill, $fy, $numbers[$fy]),
                    'customer_id' => $id, 'period_from' => $from, 'period_to' => $to, 'bill_date' => $to,
                    'due_date' => Carbon::parse($to)->addDays($dueDays)->toDateString(), 'subtotal' => $total, 'tax_amount' => '0.00',
                    'total' => $total, 'previous_balance' => $balance,
                    'payments_in_period' => $paid, 'adjustments_in_period' => '0.00', 'closing_balance' => $closing,
                    'status' => BillStatus::Finalized->value, 'finalized_at' => Carbon::parse($to)->setTime(20, 0), 'finalized_by' => $accountantId,
                    'created_by' => $accountantId, 'updated_by' => $accountantId, 'created_at' => $to, 'updated_at' => $to,
                ]);
                $balance = $closing;

                // A few days after the bill: the bill in full (85 %) or part of it.
                $payDate = Carbon::parse($to)->addDays(mt_rand(3, 12));
                if ($payDate->lt(today())) {
                    $amount = mt_rand(1, 100) <= 85 ? $total : Money::round2(Money::mul($total, '0.6'));
                    $paymentsByDate[$payDate->toDateString()] = $amount;
                }
            }

            foreach ($paymentsByDate as $date => $amount) {
                DB::table('customer_payments')->insert(['farm_id' => $farm->id, 'customer_id' => $id, 'payment_date' => $date, 'amount' => $amount,
                    'payment_mode_id' => $modes[array_rand($modes)], 'reference_no' => null, 'collected_by' => $accountantId,
                    'client_uuid' => null, 'note' => null, 'created_by' => $accountantId, 'updated_by' => $accountantId, 'created_at' => $date, 'updated_at' => $date]);
            }
        }

        // Bill items + delivery links for every bill, in two set-based statements.
        DB::statement(<<<'SQL'
            INSERT INTO bill_items (farm_id, bill_id, delivery_id, product_id, item_date, shift, description, quantity, rate, amount, created_at, updated_at)
            SELECT d.farm_id, b.id, d.id, d.product_id, d.delivery_date, d.shift, CONCAT(p.name, ' · ', IF(d.shift = 'morning', 'Morning', 'Evening')),
                   d.delivered_qty, d.rate, d.amount, b.created_at, b.created_at
            FROM bills b
            JOIN deliveries d ON d.customer_id = b.customer_id AND d.status = 'delivered' AND d.delivery_date BETWEEN b.period_from AND b.period_to
            JOIN products p ON p.id = d.product_id
            WHERE b.farm_id = ?
            SQL, [$farm->id]);
        DB::statement('UPDATE deliveries d JOIN bill_items i ON i.delivery_id = d.id SET d.bill_id = i.bill_id WHERE d.farm_id = ?', [$farm->id]);

        foreach ($numbers as $fy => $last) {
            DocumentSequence::query()->updateOrCreate(['farm_id' => $farm->id, 'type' => DocumentType::Bill->value, 'financial_year' => $fy], ['last_number' => $last]);
        }

        $allocations = app(PaymentAllocationService::class);
        foreach (array_keys($customers) as $id) {
            $allocations->allocate($id);
        }

    }
}
