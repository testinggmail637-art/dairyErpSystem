<?php

namespace App\Services;

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\MilkMovementType;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Models\Animal;
use App\Models\Expense;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Models\Product;
use App\Models\VendorPayment;
use App\Support\CurrentFarm;
use App\Support\FinancialYear;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard cards and charts (spec 7.5) for the modules built so far: milk production, milk stock, herd, expenses,
 * income vs expenses (income estimated until sales exist), vendor payable. Every figure is an SQL aggregate; the whole set is cached per farm and day for 5 minutes and
 * cleared when a milk entry, stock movement, animal, expense, vendor payment or product is written (AppServiceProvider).
 */
class DashboardService
{
    public const TTL = 300;

    public const CHART_DAYS = 30;

    public const CHART_MONTHS = 12;

    public const CHART_WEEKS = 12;

    public const CHART_YEARS = 5;

    public const PERIODS = ['day', 'week', 'month', 'year'];

    public function __construct(
        private readonly CurrentFarm $currentFarm,
        private readonly MilkDropAlertService $alerts,
    ) {}

    public static function cacheKey(int $farmId, ?string $date = null, string $part = ''): string
    {
        return 'dashboard.'.$farmId.'.'.($date ?? today()->toDateString()).$part;
    }

    public static function forget(?int $farmId): void
    {
        if ($farmId) {
            Cache::forget(self::cacheKey($farmId));
            foreach (self::PERIODS as $period) {
                Cache::forget(self::cacheKey($farmId, null, '.finance.'.$period));
            }
        }
    }

    public function data(): array
    {
        return Cache::remember(self::cacheKey($this->currentFarm->id()), self::TTL, fn () => $this->build(today()));
    }

    /** Income vs expenses for one period (day / week / month / year), cached like the rest of the dashboard. */
    public function finance(string $period): array
    {
        return Cache::remember(self::cacheKey($this->currentFarm->id(), null, '.finance.'.$period), self::TTL,
            fn () => $this->incomeVsExpenses($period, today()));
    }

    private function build(Carbon $today): array
    {
        return [
            'production' => $this->production($today),
            'stock' => $this->stock($today),
            'herd' => $this->herd(),
            'alerts' => $this->dropAlerts($today),
            'expenses' => $this->expenses($today),
            'finance' => $this->incomeVsExpenses('month', $today),
            'vendor_payable' => $this->vendorPayable(),
        ];
    }

    /**
     * Income vs expenses per day (30 days), week (12, Monday first), month (12) or financial year (5, April–March) —
     * spec 7.5. Until sales are recorded (deliveries and bills, Phase 3), income is an **estimate**: own milk produced ×
     * the current default rate of the active product of that milk type (lowest id). Profit = income − expenses.
     * Two GROUP BY queries on indexed date ranges; the bucket is computed in SQL.
     */
    private function incomeVsExpenses(string $period, Carbon $today): array
    {
        [$from, $bucket, $labels, $subtitle] = $this->buckets($period, $today);
        $zero = array_fill_keys(array_keys($labels), '0.00');
        $range = [$from->toDateString(), $today->toDateString()];

        $rates = Product::query()->active()->orderBy('id')->get(['milk_type', 'default_rate'])
            ->unique(fn (Product $p) => $p->milk_type->value)
            ->mapWithKeys(fn (Product $p) => [$p->milk_type->value => $p->default_rate])
            ->all();

        $income = $zero;
        $milkBucket = sprintf($bucket, 'milk_production_records.production_date');
        MilkProductionRecord::query()->toBase()
            ->join('animals', 'animals.id', '=', 'milk_production_records.animal_id')
            ->whereBetween('milk_production_records.production_date', $range)
            ->groupByRaw("{$milkBucket}, animals.animal_type")
            ->selectRaw("{$milkBucket} AS bucket, animals.animal_type AS type, SUM(milk_production_records.quantity_litre) AS litres")
            ->get()
            ->each(function ($row) use (&$income, $rates) {
                if (isset($income[$row->bucket])) {
                    $income[$row->bucket] = Money::add($income[$row->bucket], Money::mul((string) $row->litres, $rates[$row->type] ?? '0'));
                }
            });

        $expenses = $zero;
        $expenseBucket = sprintf($bucket, 'expense_date');
        Expense::query()->toBase()
            ->whereBetween('expense_date', $range)
            ->groupByRaw($expenseBucket)
            ->selectRaw("{$expenseBucket} AS bucket, SUM(amount) AS total")
            ->get()
            ->each(function ($row) use (&$expenses) {
                if (isset($expenses[$row->bucket])) {
                    $expenses[$row->bucket] = Money::round2((string) $row->total);
                }
            });

        $income = array_values($income);
        $expenses = array_values($expenses);
        $profit = array_map(fn ($in, $out) => Money::sub($in, $out), $income, $expenses);
        $totalIncome = Money::sum($income);
        $totalExpenses = Money::sum($expenses);
        $totalProfit = Money::sub($totalIncome, $totalExpenses);

        return [
            'period' => $period,
            'subtitle' => $subtitle,
            'labels' => array_values($labels),
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $profit,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_profit' => $totalProfit,
            'display' => ['total_income' => inr($totalIncome), 'total_expenses' => inr($totalExpenses), 'total_profit' => inr($totalProfit)],
            'rates' => $rates,
        ];
    }

    /**
     * Start date, SQL bucket expression (`%1$s` = the date column; every bucket it yields matches a PHP key),
     * bucket key => label, and the chart subtitle for a period. Bucket keys are never numeric, so PHP keeps them strings.
     *
     * @return array{0: Carbon, 1: string, 2: array<string, string>, 3: string}
     */
    private function buckets(string $period, Carbon $today): array
    {
        $labels = [];

        switch ($period) {
            case 'day':
                $from = $today->copy()->subDays(self::CHART_DAYS - 1);
                for ($d = $from->copy(); $d->lte($today); $d->addDay()) {
                    $labels[$d->toDateString()] = $d->format('d M');
                }

                return [$from, 'DATE(%1$s)', $labels, __('Last :n days', ['n' => self::CHART_DAYS])];

            case 'week':
                $from = $today->copy()->startOfWeek(Carbon::MONDAY)->subWeeks(self::CHART_WEEKS - 1);
                for ($d = $from->copy(); $d->lte($today); $d->addWeek()) {
                    $labels[$d->toDateString()] = $d->format('d M');
                }

                return [$from, 'DATE(DATE_SUB(%1$s, INTERVAL WEEKDAY(%1$s) DAY))', $labels, __('Last :n weeks, Monday to Sunday', ['n' => self::CHART_WEEKS])];

            case 'year':
                $from = Carbon::parse(FinancialYear::start($today))->subYears(self::CHART_YEARS - 1);
                for ($d = $from->copy(); $d->lte($today); $d->addYear()) {
                    $labels['fy'.$d->year] = 'FY '.FinancialYear::label($d);
                }

                return [$from, "CONCAT('fy', YEAR(%1\$s) - (MONTH(%1\$s) < 4))", $labels, __('Last :n financial years, April to March', ['n' => self::CHART_YEARS])];

            default:
                $from = $today->copy()->startOfMonth()->subMonths(self::CHART_MONTHS - 1);
                for ($d = $from->copy(); $d->lte($today); $d->addMonth()) {
                    $labels[$d->format('Y-m')] = $d->format('M y');
                }

                return [$from, "DATE_FORMAT(%1\$s, '%%Y-%%m')", $labels, __('Last :n months', ['n' => self::CHART_MONTHS])];
        }
    }

    /** Today / yesterday / month-to-date litres by milk type, plus the daily series for the last 30 days. */
    private function production(Carbon $today): array
    {
        $from = $today->copy()->subDays(self::CHART_DAYS)->toDateString(); // chart: the last 30 complete days (today is still being entered)
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $start = min($from, $monthStart);

        $rows = MilkProductionRecord::query()->toBase()
            ->join('animals', 'animals.id', '=', 'milk_production_records.animal_id')
            ->where('milk_production_records.production_date', '>=', $start)
            ->where('milk_production_records.production_date', '<=', $today->toDateString())
            ->groupBy('milk_production_records.production_date', 'animals.animal_type')
            ->selectRaw('milk_production_records.production_date AS day, animals.animal_type AS type, SUM(milk_production_records.quantity_litre) AS litres')
            ->get();

        $days = [];
        for ($d = Carbon::parse($from); $d->lt($today); $d->addDay()) {
            $days[$d->toDateString()] = array_fill_keys(array_column(MilkType::cases(), 'value'), '0.00');
        }

        $zero = array_fill_keys(array_column(MilkType::cases(), 'value'), '0.00');
        $todayTotals = $zero;
        $yesterdayTotals = $zero;
        $month = $zero;
        $yesterday = $today->copy()->subDay()->toDateString();

        foreach ($rows as $row) {
            $day = Carbon::parse($row->day)->toDateString();
            $litres = Money::round2((string) $row->litres);

            if (isset($days[$day])) {
                $days[$day][$row->type] = $litres;
            }
            if ($day >= $monthStart) {
                $month[$row->type] = Money::add($month[$row->type], $litres);
            }
            if ($day === $today->toDateString()) {
                $todayTotals[$row->type] = $litres;
            }
            if ($day === $yesterday) {
                $yesterdayTotals[$row->type] = $litres;
            }
        }

        $total = Money::sum($todayTotals);
        $previous = Money::sum($yesterdayTotals);

        return [
            'today' => $todayTotals + ['total' => $total],
            'yesterday_total' => $previous,
            'change_pct' => Money::isZero($previous) || Money::isZero($total) ? null : Money::round(bcdiv(bcmul(bcsub($total, $previous, 4), '100', 4), $previous, 4), 1),
            'month_total' => Money::sum($month),
            'chart' => [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->format('d M'), array_keys($days)),
                'series' => collect(MilkType::cases())->mapWithKeys(fn (MilkType $t) => [$t->value => array_column($days, $t->value)])->all(),
                'total' => $chartTotal = Money::sum(array_merge(...array_map('array_values', array_values($days)))),
                'average' => Money::div($chartTotal, (string) self::CHART_DAYS),
            ],
        ];
    }

    /** Current balance per source × milk type and today's wastage. */
    private function stock(Carbon $today): array
    {
        $balances = [];
        foreach (MilkSource::cases() as $source) {
            foreach (MilkType::cases() as $type) {
                $balances[$source->value.'.'.$type->value] = ['source' => $source->label(), 'type' => $type->label(), 'litres' => '0.00'];
            }
        }

        MilkStockMovement::query()->toBase()
            ->groupBy('stock_source', 'milk_type')
            ->selectRaw('stock_source, milk_type, SUM(quantity_in) - SUM(quantity_out) AS balance')
            ->get()
            ->each(function ($row) use (&$balances) {
                $balances[$row->stock_source.'.'.$row->milk_type]['litres'] = Money::round2((string) $row->balance);
            });

        $wastage = MilkStockMovement::query()->toBase()
            ->where('movement_date', $today->toDateString())
            ->where('movement_type', MilkMovementType::Wastage->value)
            ->sum('quantity_out');

        return [
            'rows' => array_values($balances),
            'total' => Money::sum(array_column($balances, 'litres')),
            'wastage_today' => Money::round2((string) $wastage),
        ];
    }

    /** Active animals by lifecycle stage. */
    private function herd(): array
    {
        $counts = Animal::query()->toBase()
            ->where('status', AnimalStatus::Active->value)
            ->groupBy('lifecycle_stage')
            ->selectRaw('lifecycle_stage, COUNT(*) AS cnt')
            ->pluck('cnt', 'lifecycle_stage');

        $stages = collect(LifecycleStage::cases())
            ->map(fn (LifecycleStage $s) => ['label' => $s->label(), 'count' => (int) ($counts[$s->value] ?? 0)])
            ->filter(fn ($s) => $s['count'] > 0)
            ->sortByDesc('count') // the biggest group gets the primary colour
            ->values()
            ->all();

        return [
            'active' => (int) $counts->sum(),
            'milking' => (int) ($counts[LifecycleStage::Milking->value] ?? 0),
            'stages' => $stages,
        ];
    }

    private function dropAlerts(Carbon $today): array
    {
        $alerts = $this->alerts->alerts($today->toDateString());

        return [
            'count' => $alerts->count(),
            'top' => $alerts->take(5)->map(fn ($a) => [
                'animal_id' => $a->animal_id,
                'animal' => trim(($a->animal?->tag_no ?? '').' '.($a->animal?->name ?? '')),
                'shift' => $a->shift->label(),
                'today' => $a->today,
                'average' => $a->average,
                'drop' => $a->drop,
            ])->values()->all(),
        ];
    }

    /** Today, this month, last month and this month by category. */
    private function expenses(Carbon $today): array
    {
        $monthStart = $today->copy()->startOfMonth();
        $lastMonth = $monthStart->copy()->subMonth();

        $monthly = Expense::query()->toBase()
            ->whereNull('deleted_at')
            ->whereBetween('expense_date', [$lastMonth->toDateString(), $today->toDateString()])
            ->groupByRaw("DATE_FORMAT(expense_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total")
            ->pluck('total', 'ym');

        $byCategory = Expense::query()->toBase()
            ->join('expense_categories AS c', 'c.id', '=', 'expenses.category_id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$monthStart->toDateString(), $today->toDateString()])
            ->groupBy('c.id', 'c.name')
            ->orderByRaw('SUM(expenses.amount) DESC')
            ->selectRaw('c.name, SUM(expenses.amount) AS total')
            ->get();

        $top = $byCategory->take(6);
        $other = Money::sum($byCategory->slice(6)->pluck('total')->map(fn ($v) => (string) $v));
        $categories = $top->map(fn ($r) => ['label' => $r->name, 'amount' => Money::round2((string) $r->total)])->all();
        if (Money::compare($other, '0') > 0) {
            $categories[] = ['label' => __('Others'), 'amount' => $other];
        }

        $todayTotal = Expense::query()->toBase()
            ->whereNull('deleted_at')
            ->where('expense_date', $today->toDateString())
            ->sum('amount');

        return [
            'today' => Money::round2((string) $todayTotal),
            'month' => Money::round2((string) ($monthly[$monthStart->format('Y-m')] ?? 0)),
            'last_month' => Money::round2((string) ($monthly[$lastMonth->format('Y-m')] ?? 0)),
            'categories' => $categories,
        ];
    }

    /** Σ vendor opening balances + credit expenses − vendor payments (same basis as the vendor ledger). */
    private function vendorPayable(): string
    {
        $farmId = $this->currentFarm->id();

        $opening = DB::table('vendors')->where('farm_id', $farmId)->whereNull('deleted_at')->sum('opening_balance');
        $credit = Expense::query()->toBase()
            ->whereNull('deleted_at')->where('is_credit', true)->whereNotNull('vendor_id')
            ->sum('amount');
        $paid = VendorPayment::query()->toBase()->sum('amount');

        return Money::sub(Money::add((string) $opening, (string) $credit), (string) $paid);
    }
}
