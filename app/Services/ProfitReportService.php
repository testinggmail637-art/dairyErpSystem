<?php

namespace App\Services;

use App\Enums\AnimalSaleStatus;
use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Enums\MilkSource;
use App\Enums\QualityStatus;
use App\Models\Animal;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Bill;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\MilkCollection;
use App\Models\MilkProductionRecord;
use App\Models\Section;
use App\Reports\ReportTable;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Profitability reports (spec 7.6 #7–#10), all for management: simple P&L, cost per litre of own milk, estimated
 * contribution per milking animal, collection center margin. Every figure is an SQL sum on indexed date ranges.
 */
class ProfitReportService
{
    public const REPORTS = ['pnl', 'cost-per-litre', 'animals', 'collection-margin'];

    public const MAX_MONTHS = 24;

    public function __construct(private readonly AnimalPnlService $pnl) {}

    /** Per month: HERD section expenses ÷ own production litres. */
    public function costPerLitre(string $from, string $to): ReportTable
    {
        $months = $this->months($from, $to);
        $month = fn (string $column) => "DATE_FORMAT({$column}, '%Y-%m')";
        $expenses = $this->sectionExpenses('HERD', $from, $to)->groupByRaw($month('expense_date'))
            ->selectRaw($month('expense_date').' AS month, SUM(amount) AS total')->pluck('total', 'month');
        $litres = MilkProductionRecord::query()->toBase()->whereBetween('production_date', [$from, $to])
            ->groupByRaw($month('production_date'))->selectRaw($month('production_date').' AS month, SUM(quantity_litre) AS total')->pluck('total', 'month');

        $rows = [];
        foreach ($months as $key => $label) {
            $rows[] = $this->costRow($label, Money::round2((string) ($expenses[$key] ?? 0)), Money::round2((string) ($litres[$key] ?? 0)));
        }

        return new ReportTable(
            __('Cost per litre (own milk)'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Month')),
                ReportTable::column(__('Herd expenses'), ReportTable::MONEY),
                ReportTable::column(__('Milk produced (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Cost per litre'), ReportTable::MONEY),
            ],
            $rows,
            $this->costRow(__('Total'), Money::sum(array_column($rows, 1)), Money::sum(array_column($rows, 2))),
            [__('Cost per litre = expenses of the Own Farm / Herd section ÷ litres produced by the herd in the same month. Expenses booked to other sections (e.g. salaries under Office) are not included.')],
        );
    }

    /**
     * Estimated contribution of each animal milked in the period: milk value (litres × realized selling rate, as the
     * animal P&L) − feed recorded for the animal − expenses booked to the animal − a share of the herd overhead.
     * Overhead = HERD expenses without an animal − the feed already charged to animals (feed purchases are HERD
     * expenses, so this avoids counting that feed twice), shared by days in milk (days with a milk entry).
     */
    public function profitPerAnimal(string $from, string $to): ReportTable
    {
        $days = MilkProductionRecord::query()->toBase()->whereBetween('production_date', [$from, $to])
            ->groupBy('animal_id')->selectRaw('animal_id, COUNT(DISTINCT production_date) AS days')->pluck('days', 'animal_id');
        $animals = Animal::query()->whereIn('id', $days->keys())->orderBy('tag_no')->get(['id', 'tag_no', 'name', 'animal_type']);
        $ids = $animals->modelKeys();

        $milk = $this->pnl->milk($animals, $from, $to);
        $feed = $this->pnl->feedCost($ids, $from, $to);
        $own = Expense::query()->toBase()->whereIn('animal_id', $ids)->whereBetween('expense_date', [$from, $to])
            ->groupBy('animal_id')->selectRaw('animal_id, SUM(amount) AS total')->pluck('total', 'animal_id');
        $herd = (string) $this->sectionExpenses('HERD', $from, $to)->whereNull('animal_id')->sum('amount');
        $pool = Money::sub($herd, Money::sum($feed));
        $pool = Money::isNegative($pool) ? '0.00' : $pool;
        $totalDays = (string) $days->sum();

        $shared = '0.00';
        $rows = $animals->values()->map(function (Animal $a, int $i) use ($animals, $days, $milk, $feed, $own, $pool, $totalDays, &$shared) {
            $share = $i === $animals->count() - 1 ? Money::sub($pool, $shared) // last one takes the rounding
                : Money::round2(bcdiv(bcmul($pool, (string) $days[$a->id], 6), $totalDays, 6));
            $shared = Money::add($shared, $share);
            $value = $milk[$a->id]['value'] ?? '0.00';
            $feedCost = $feed[$a->id] ?? '0.00';
            $expenses = Money::round2((string) ($own[$a->id] ?? 0));

            return [
                $a->name ? "{$a->tag_no} · {$a->name}" : $a->tag_no, $a->animal_type->label(), (int) $days[$a->id],
                $milk[$a->id]['litres'] ?? '0.00', $value, $feedCost, $expenses, $share,
                Money::sub($value, Money::add($feedCost, $expenses, $share)),
            ];
        });

        return new ReportTable(
            __('Profit per animal (estimate)'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Animal')),
                ReportTable::column(__('Type')),
                ReportTable::column(__('Days in milk'), ReportTable::NUMBER),
                ReportTable::column(__('Milk (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Milk value'), ReportTable::MONEY),
                ReportTable::column(__('Feed'), ReportTable::MONEY),
                ReportTable::column(__('Animal expenses'), ReportTable::MONEY),
                ReportTable::column(__('Herd overhead'), ReportTable::MONEY),
                ReportTable::column(__('Contribution'), ReportTable::MONEY),
            ],
            $rows->all(),
            $rows->isEmpty() ? null : [__('Total (:count)', ['count' => $rows->count()]), '', (int) $totalDays,
                ...array_map(fn ($i) => Money::sum($rows->pluck($i)), range(3, 8))],
            [
                __('Estimate — not accounts. Milk value = litres × average selling rate of own milk of that type in that month.'),
                __('Feed = feed recorded for the animal × average purchase cost. Animal expenses = expenses booked to the animal (treatments, vaccinations …).'),
                __('Herd overhead = Own Farm / Herd expenses not booked to an animal, less the feed already charged above, shared by days in milk.'),
            ],
            emptyText: __('No milk entries in this period.'),
        );
    }

    /** Per month: collection milk sold − milk bought from farmers − COLLECTION section expenses. */
    public function collectionMargin(string $from, string $to): ReportTable
    {
        $months = $this->months($from, $to);
        $month = fn (string $column) => "DATE_FORMAT({$column}, '%Y-%m')";
        $bought = MilkCollection::query()->toBase()->whereBetween('collection_date', [$from, $to])
            ->where('quality_status', QualityStatus::Accepted->value)->groupByRaw($month('collection_date'))
            ->selectRaw($month('collection_date').' AS month, SUM(quantity_litre) AS litres, SUM(amount) AS amount')->get()->keyBy('month');
        $sold = Delivery::query()->toBase()->whereBetween('delivery_date', [$from, $to])
            ->where('status', DeliveryStatus::Delivered->value)->where('stock_source', MilkSource::Collection->value)
            ->groupByRaw($month('delivery_date'))
            ->selectRaw($month('delivery_date').' AS month, SUM(delivered_qty) AS litres, SUM(amount) AS amount')->get()->keyBy('month');
        $expenses = $this->sectionExpenses('COLLECTION', $from, $to)->groupByRaw($month('expense_date'))
            ->selectRaw($month('expense_date').' AS month, SUM(amount) AS total')->pluck('total', 'month');

        $rows = [];
        foreach ($months as $key => $label) {
            $values = [
                Money::round2((string) ($bought[$key]->litres ?? 0)), Money::round2((string) ($sold[$key]->litres ?? 0)),
                Money::round2((string) ($sold[$key]->amount ?? 0)), Money::round2((string) ($bought[$key]->amount ?? 0)),
                Money::round2((string) ($expenses[$key] ?? 0)),
            ];
            $rows[] = [$label, ...$values, Money::sub($values[2], Money::add($values[3], $values[4]))];
        }

        return new ReportTable(
            __('Collection center margin'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Month')),
                ReportTable::column(__('Collected (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Sold (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Sales value'), ReportTable::MONEY),
                ReportTable::column(__('Paid to farmers'), ReportTable::MONEY),
                ReportTable::column(__('Center expenses'), ReportTable::MONEY),
                ReportTable::column(__('Margin'), ReportTable::MONEY),
            ],
            $rows,
            [__('Total'), ...array_map(fn ($i) => Money::sum(array_column($rows, $i)), range(1, 6))],
            [__('Margin = deliveries of collection milk − accepted farmer milk (amount on the collection entry) − Collection Center section expenses, by date. Milk still in stock at the end of a month is not valued.')],
        );
    }

    /**
     * Simple P&L (spec 7.6 #10), a management summary by date of each record: income = finalized bills (bill date) +
     * delivered milk not in a finalized bill ("direct deliveries", delivery date) + completed animal sales (net);
     * costs = accepted farmer milk + expenses (by category) + animal purchases. Loan principal is not a cost.
     */
    public function profitAndLoss(string $from, string $to): ReportTable
    {
        $range = [$from, $to];
        $bills = (string) Bill::query()->toBase()->where('status', BillStatus::Finalized->value)->whereBetween('bill_date', $range)->sum('total');
        $direct = (string) Delivery::query()->toBase()
            ->leftJoin('bills as b', 'b.id', '=', 'deliveries.bill_id')
            ->where('deliveries.status', DeliveryStatus::Delivered->value)
            ->whereBetween('deliveries.delivery_date', $range)
            ->where(fn ($q) => $q->whereNull('deliveries.bill_id')->orWhere('b.status', '!=', BillStatus::Finalized->value))
            ->sum('deliveries.amount');
        $animalSales = (string) AnimalSale::query()->toBase()->where('status', AnimalSaleStatus::Completed->value)->whereBetween('sale_date', $range)->sum('net_amount');
        $farmerMilk = (string) MilkCollection::query()->toBase()->where('quality_status', QualityStatus::Accepted->value)
            ->whereBetween('collection_date', $range)->sum('amount');
        $byCategory = Expense::query()->toBase()->whereBetween('expense_date', $range)
            ->groupBy('category_id')->selectRaw('category_id, SUM(amount) AS total')->pluck('total', 'category_id');
        $animalPurchases = (string) AnimalPurchase::query()->toBase()->whereBetween('purchase_date', $range)->sum('total_amount');

        $income = [
            __('Milk sales — finalized bills') => Money::round2($bills),
            __('Milk sales — delivered, not yet billed') => Money::round2($direct),
            __('Animal sales (net of selling costs)') => Money::round2($animalSales),
        ];
        $costs = [__('Milk bought from farmers') => Money::round2($farmerMilk)];
        foreach (ExpenseCategory::query()->whereIn('id', $byCategory->keys())->ordered()->pluck('name', 'id') as $id => $name) {
            $costs[__('Expenses — :category', ['category' => $name])] = Money::round2((string) $byCategory[$id]);
        }
        $costs[__('Animal purchases')] = Money::round2($animalPurchases);

        $totalIncome = Money::sum($income);
        $totalCosts = Money::sum($costs);
        $share = fn (string $amount) => Money::isZero($totalIncome) ? null : Money::round(bcdiv(bcmul($amount, '100', 4), $totalIncome, 4), 1);
        $lines = fn (array $items) => array_map(fn ($label, $amount) => [$label, $amount, $share($amount)], array_keys($items), $items);

        $rows = [
            [__('Income'), null, null],
            ...$lines($income),
            [__('Total income'), $totalIncome, $share($totalIncome)],
            [__('Costs'), null, null],
            ...$lines($costs),
            [__('Total costs'), $totalCosts, $share($totalCosts)],
            [__('Net profit'), $net = Money::sub($totalIncome, $totalCosts), $share($net)],
        ];
        $strong = [0, count($income) + 1, count($income) + 2, count($rows) - 2, count($rows) - 1];

        return new ReportTable(
            __('Profit & loss (management summary)'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Item')),
                ReportTable::column(__('Amount'), ReportTable::MONEY),
                ReportTable::column(__('% of income'), ReportTable::PERCENT),
            ],
            $rows,
            null,
            [
                __('Management summary, not statutory accounts.'),
                __('By date of each record: bills by bill date, milk not yet billed by delivery date, farmer milk by collection date. Loan principal repaid is not a cost (loan interest is an expense).'),
            ],
            $strong,
        );
    }

    private function sectionExpenses(string $code, string $from, string $to): Builder
    {
        return Expense::query()->toBase()->whereBetween('expense_date', [$from, $to])
            ->where('section_id', Section::query()->where('code', $code)->value('id') ?? 0);
    }

    private function costRow(string $label, string $expenses, string $litres): array
    {
        return [$label, $expenses, $litres, Money::isZero($litres) ? null : Money::div($expenses, $litres)];
    }

    /** @return array<string, string> 'Y-m' => 'Apr 26' */
    private function months(string $from, string $to): array
    {
        $months = [];
        for ($m = Carbon::parse($from)->startOfMonth(); $m->lte(Carbon::parse($to)); $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M y');
        }
        if (count($months) > self::MAX_MONTHS) {
            throw ValidationException::withMessages(['from' => __('Choose a period of at most :n months.', ['n' => self::MAX_MONTHS])]);
        }

        return $months;
    }

    private function period(string $from, string $to): string
    {
        return fmt_date($from).' – '.fmt_date($to);
    }
}
