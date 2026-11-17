<?php

namespace App\Services;

use App\Enums\AnimalType;
use App\Enums\MilkMovementType;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Models\Calving;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Reports\ReportTable;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Milk production reports (spec 7.3 / 7.6): daily animal × shift grid with totals by milk type, monthly day-by-day,
 * animal-wise and lactation-wise for any period, and the milk stock balance per source × type. Figures are SQL aggregates over the farm's milk entries joined to their animal (the milk
 * type is the animal's type); rows are never loaded into PHP.
 */
class MilkReportService
{
    public const REPORTS = ['daily', 'monthly', 'animals', 'lactations', 'balance'];

    /** One day: every animal milked, morning / evening / total, a subtotal per milk type. */
    public function daily(string $date, ?string $milkType = null): ReportTable
    {
        $lines = $this->entries($milkType)
            ->where('milk_production_records.production_date', $date)
            ->groupBy('animals.id', 'animals.tag_no', 'animals.name', 'animals.animal_type')
            ->orderByRaw("FIELD(animals.animal_type, 'cow', 'buffalo')")->orderBy('animals.tag_no')
            ->select(['animals.tag_no', 'animals.name', 'animals.animal_type'])
            ->tap(fn (Builder $q) => $this->shiftSums($q))
            ->get();

        $rows = [];
        $strong = [];

        foreach ($lines->groupBy('animal_type') as $type => $group) {
            foreach ($group as $line) {
                $rows[] = [$this->label($line), AnimalType::from($type)->label(), $this->qty($line->morning), $this->qty($line->evening), $this->qty($line->total)];
            }
            $strong[] = count($rows);
            $rows[] = [
                __(':type total (:count)', ['type' => AnimalType::from($type)->label(), 'count' => $group->count()]), '',
                Money::sum($group->pluck('morning')), Money::sum($group->pluck('evening')), Money::sum($group->pluck('total')),
            ];
        }

        return new ReportTable(
            __('Daily milk production'),
            fmt_date($date).$this->typeSuffix($milkType),
            [
                ReportTable::column(__('Animal')),
                ReportTable::column(__('Type')),
                ReportTable::column(__('Morning (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Evening (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Total (L)'), ReportTable::QUANTITY),
            ],
            $rows,
            [__('Total (:count)', ['count' => $lines->count()]), '', Money::sum($lines->pluck('morning')), Money::sum($lines->pluck('evening')), Money::sum($lines->pluck('total'))],
            strongRows: $strong,
            emptyText: __('No milk entries on this day.'),
        );
    }

    /** One month day by day (up to today): litres per milk type and shift, animals milked. */
    public function monthly(string $month, ?string $milkType = null): ReportTable
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth()->min(today());
        $range = [$from->toDateString(), $to->toDateString()];

        $cells = $this->entries($milkType)
            ->whereBetween('milk_production_records.production_date', $range)
            ->groupBy('milk_production_records.production_date', 'animals.animal_type', 'milk_production_records.shift')
            ->selectRaw('milk_production_records.production_date AS day, animals.animal_type AS type, milk_production_records.shift AS shift')
            ->selectRaw('SUM(milk_production_records.quantity_litre) AS litres')
            ->get()
            ->groupBy('day');

        $animals = $this->entries($milkType)
            ->whereBetween('milk_production_records.production_date', $range)
            ->groupBy('milk_production_records.production_date')
            ->selectRaw('milk_production_records.production_date AS day, COUNT(DISTINCT milk_production_records.animal_id) AS animals')
            ->pluck('animals', 'day');

        $rows = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $c = $cells->get($day->toDateString(), collect());
            $rows[] = [
                $day->toDateString(),
                Money::sum($c->where('type', AnimalType::Cow->value)->pluck('litres')),
                Money::sum($c->where('type', AnimalType::Buffalo->value)->pluck('litres')),
                Money::sum($c->where('shift', 'morning')->pluck('litres')),
                Money::sum($c->where('shift', 'evening')->pluck('litres')),
                Money::sum($c->pluck('litres')),
                (int) ($animals[$day->toDateString()] ?? 0),
            ];
        }

        $sum = fn (int $i) => Money::sum(array_column($rows, $i));

        return new ReportTable(
            __('Monthly milk production'),
            $from->translatedFormat('F Y').$this->typeSuffix($milkType),
            [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Cow (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Buffalo (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Morning (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Evening (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Total (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Animals'), ReportTable::NUMBER),
            ],
            $rows,
            [__('Total'), $sum(1), $sum(2), $sum(3), $sum(4), $sum(5), ''],
            $from->isFuture() ? [] : [__('Days without entries show 0.')],
            emptyText: __('This month has not started yet.'),
        );
    }

    /** Each animal over a period: days milked, morning / evening / total, average per milked day. Largest first. */
    public function animalWise(string $from, string $to, ?string $milkType = null): ReportTable
    {
        $lines = $this->entries($milkType)
            ->whereBetween('milk_production_records.production_date', [$from, $to])
            ->groupBy('animals.id', 'animals.tag_no', 'animals.name', 'animals.animal_type')
            ->select(['animals.tag_no', 'animals.name', 'animals.animal_type'])
            ->selectRaw('COUNT(DISTINCT milk_production_records.production_date) AS days')
            ->tap(fn (Builder $q) => $this->shiftSums($q))
            ->orderByDesc('total')->orderBy('animals.tag_no')
            ->get();

        $rows = $lines->map(fn ($l) => [
            $this->label($l),
            AnimalType::from($l->animal_type)->label(),
            (int) $l->days,
            $this->qty($l->morning),
            $this->qty($l->evening),
            $this->qty($l->total),
            (int) $l->days > 0 ? Money::div((string) $l->total, (string) $l->days) : '0.00',
        ])->all();

        return new ReportTable(
            __('Animal-wise milk production'),
            $this->period($from, $to).$this->typeSuffix($milkType),
            [
                ReportTable::column(__('Animal')),
                ReportTable::column(__('Type')),
                ReportTable::column(__('Days milked'), ReportTable::NUMBER),
                ReportTable::column(__('Morning (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Evening (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Total (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Avg / day (L)'), ReportTable::QUANTITY),
            ],
            $rows,
            [__('Total (:count)', ['count' => $lines->count()]), '', '', Money::sum($lines->pluck('morning')), Money::sum($lines->pluck('evening')), Money::sum($lines->pluck('total')), ''],
            [__('Average per day = total ÷ days with at least one entry.')],
            emptyText: __('No milk entries in this period.'),
        );
    }

    /**
     * Each animal's milk in the period split by lactation: an entry belongs to the latest calving on or before its date
     * (correlated subquery on the calvings (mother_id, calving_date) index). Lactation no. = the animal's current
     * lactation − calvings recorded after that one; entries before any recorded calving belong to the opening lactation.
     */
    public function lactationWise(string $from, string $to, ?string $milkType = null): ReportTable
    {
        $daily = $this->entries($milkType)
            ->whereBetween('milk_production_records.production_date', [$from, $to])
            ->select('milk_production_records.animal_id')
            ->selectRaw('milk_production_records.production_date AS day')
            ->selectRaw('(SELECT MAX(c.calving_date) FROM calvings c WHERE c.mother_id = milk_production_records.animal_id AND c.calving_date <= milk_production_records.production_date) AS calved_on')
            ->selectRaw('SUM(milk_production_records.quantity_litre) AS litres')
            ->groupBy('milk_production_records.animal_id', 'milk_production_records.production_date', 'calved_on');

        $lines = DB::query()->fromSub($daily, 'd')
            ->join('animals', 'animals.id', '=', 'd.animal_id')
            ->groupBy('d.animal_id', 'd.calved_on', 'animals.tag_no', 'animals.name', 'animals.animal_type', 'animals.lactation_number')
            ->select(['d.animal_id', 'd.calved_on', 'animals.tag_no', 'animals.name', 'animals.animal_type', 'animals.lactation_number'])
            ->selectRaw('COUNT(*) AS days, SUM(d.litres) AS total, MAX(d.litres) AS peak, MAX(d.day) AS last_day')
            ->orderBy('animals.tag_no')->orderBy('d.calved_on')
            ->get();

        $calvings = Calving::query()->whereIn('mother_id', $lines->pluck('animal_id')->unique())
            ->get(['mother_id', 'calving_date'])->groupBy('mother_id');

        $rows = $lines->map(function ($l) use ($calvings) {
            $calvedOn = $l->calved_on ? Carbon::parse($l->calved_on)->toDateString() : null;
            $later = ($calvings[$l->animal_id] ?? collect())
                ->filter(fn (Calving $c) => $calvedOn === null || $c->calving_date->toDateString() > $calvedOn)->count();
            $number = (int) $l->lactation_number - $later;

            return [
                $this->label($l),
                AnimalType::from($l->animal_type)->label(),
                $number > 0 ? $number : null,
                $calvedOn,
                (int) $l->days,
                $this->qty($l->total),
                Money::div((string) $l->total, (string) $l->days),
                $this->qty($l->peak),
                $calvedOn ? (int) Carbon::parse($calvedOn)->diffInDays(Carbon::parse($l->last_day)) + 1 : null,
            ];
        })->all();

        return new ReportTable(
            __('Lactation-wise milk production'),
            $this->period($from, $to).$this->typeSuffix($milkType),
            [
                ReportTable::column(__('Animal')),
                ReportTable::column(__('Type')),
                ReportTable::column(__('Lactation'), ReportTable::NUMBER),
                ReportTable::column(__('Calved on'), ReportTable::DATE),
                ReportTable::column(__('Days milked'), ReportTable::NUMBER),
                ReportTable::column(__('Total (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Avg / day (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Peak day (L)'), ReportTable::QUANTITY),
                ReportTable::column(__('Days in milk'), ReportTable::NUMBER),
            ],
            $rows,
            [__('Total (:count)', ['count' => $lines->count()]), '', '', '', $lines->sum(fn ($l) => (int) $l->days), Money::sum($lines->pluck('total')), '', '', ''],
            [
                __('Milk is counted in the lactation that started with the latest calving on or before its date.'),
                __('Days in milk = calving date to the last entry in the period. Blank = milk before the first calving recorded in the app.'),
            ],
            emptyText: __('No milk entries in this period.'),
        );
    }

    /**
     * Milk stock balance per source × milk type for a period (spec 7.6 #3) from the stock ledger: opening, each
     * movement type, closing, the lowest closing of a day in the period and the days that closed below zero
     * (running total by an SQL window over the (farm, source, type, date) index).
     */
    public function balance(string $from, string $to, ?string $milkType = null): ReportTable
    {
        $ledger = fn () => MilkStockMovement::query()->toBase()->when($milkType, fn ($q, $v) => $q->where('milk_type', $v));
        $key = fn ($r) => $r->stock_source.'.'.$r->milk_type;

        $opening = $ledger()->where('movement_date', '<', $from)->groupBy('stock_source', 'milk_type')
            ->selectRaw('stock_source, milk_type, SUM(quantity_in) - SUM(quantity_out) AS qty')->get()->keyBy($key);

        $moves = $ledger()->whereBetween('movement_date', [$from, $to])->groupBy('stock_source', 'milk_type', 'movement_type')
            ->selectRaw('stock_source, milk_type, movement_type, SUM(quantity_in) AS qty_in, SUM(quantity_out) AS qty_out')->get()->groupBy($key);

        $running = $ledger()->where('movement_date', '<=', $to)->groupBy('stock_source', 'milk_type', 'movement_date')
            ->selectRaw('stock_source, milk_type, movement_date')
            ->selectRaw('SUM(SUM(quantity_in) - SUM(quantity_out)) OVER (PARTITION BY stock_source, milk_type ORDER BY movement_date) AS closing');
        $lows = DB::query()->fromSub($running, 'r')->where('movement_date', '>=', $from)->groupBy('stock_source', 'milk_type')
            ->selectRaw('stock_source, milk_type, MIN(closing) AS lowest, SUM(closing < 0) AS negative_days, MIN(CASE WHEN closing < 0 THEN movement_date END) AS first_negative')
            ->get()->keyBy($key);

        $types = [MilkMovementType::Production, MilkMovementType::Collection, MilkMovementType::AdjustmentIn, MilkMovementType::Delivery,
            MilkMovementType::Wastage, MilkMovementType::HomeUse, MilkMovementType::CalfFeeding, MilkMovementType::AdjustmentOut];
        $rows = [];
        $strong = [];
        $notes = [];
        $sums = array_fill(0, count($types) + 2, '0.00'); // opening, movement types, closing
        $negativeTotal = 0;

        foreach (MilkSource::cases() as $source) {
            foreach (MilkType::cases() as $type) {
                $k = $source->value.'.'.$type->value;
                if (! isset($opening[$k]) && ! isset($moves[$k])) {
                    continue;
                }
                $byType = ($moves[$k] ?? collect())->keyBy('movement_type');
                $amounts = array_map(fn (MilkMovementType $t) => $this->qty($t->isIn() ? ($byType[$t->value]->qty_in ?? 0) : ($byType[$t->value]->qty_out ?? 0)), $types);
                $open = $this->qty($opening[$k]->qty ?? 0);
                $closing = $open;
                foreach ($types as $i => $t) {
                    $closing = $t->isIn() ? Money::add($closing, $amounts[$i]) : Money::sub($closing, $amounts[$i]);
                }
                $low = $lows[$k] ?? null;
                $negativeDays = (int) ($low->negative_days ?? 0);
                $negativeTotal += $negativeDays;

                foreach ([$open, ...$amounts, $closing] as $i => $v) {
                    $sums[$i] = Money::add($sums[$i], $v);
                }
                if ($negativeDays > 0) {
                    $strong[] = count($rows);
                    $notes[] = __('⚠ :stock went below zero on :n day(s), first on :date — check for missing entries.', [
                        'stock' => $source->label().' · '.$type->label(), 'n' => $negativeDays, 'date' => fmt_date($low->first_negative),
                    ]);
                }
                $rows[] = [$source->label(), $type->label(), $open, ...$amounts, $closing, $low ? $this->qty($low->lowest) : $open, $negativeDays];
            }
        }

        $q = fn (string $label) => ReportTable::column($label, ReportTable::QUANTITY);

        return new ReportTable(
            __('Milk balance'),
            $this->period($from, $to).$this->typeSuffix($milkType),
            [
                ReportTable::column(__('Stock')), ReportTable::column(__('Milk type')), $q(__('Opening (L)')),
                ...array_map(fn (MilkMovementType $t) => $q($t->label()), $types),
                $q(__('Closing (L)')), $q(__('Lowest closing (L)')), ReportTable::column(__('Days below zero'), ReportTable::NUMBER),
            ],
            $rows,
            $rows ? [__('Total'), '', ...$sums, '', $negativeTotal] : null,
            [__('Closing = opening + milk in − milk out, from the milk stock ledger. Lowest closing and days below zero count the days with a movement.'), ...$notes],
            $strong,
            emptyText: __('No milk stock movements.'),
        );
    }

    /** The farm's milk entries joined to their animal, optionally of one milk type. */
    private function entries(?string $milkType): Builder
    {
        return MilkProductionRecord::query()->toBase()
            ->join('animals', 'animals.id', '=', 'milk_production_records.animal_id')
            ->when($milkType, fn ($q, $v) => $q->where('animals.animal_type', $v));
    }

    private function shiftSums(Builder $query): void
    {
        $query->selectRaw("COALESCE(SUM(CASE WHEN milk_production_records.shift = 'morning' THEN milk_production_records.quantity_litre END), 0) AS morning")
            ->selectRaw("COALESCE(SUM(CASE WHEN milk_production_records.shift = 'evening' THEN milk_production_records.quantity_litre END), 0) AS evening")
            ->selectRaw('SUM(milk_production_records.quantity_litre) AS total');
    }

    private function label(object $line): string
    {
        return $line->name ? "{$line->tag_no} · {$line->name}" : (string) $line->tag_no;
    }

    private function qty(mixed $value): string
    {
        return Money::round2((string) $value);
    }

    private function typeSuffix(?string $milkType): string
    {
        return $milkType ? ' · '.AnimalType::from($milkType)->label() : '';
    }

    private function period(string $from, string $to): string
    {
        return $from === $to ? fmt_date($from) : fmt_date($from).' – '.fmt_date($to);
    }
}
