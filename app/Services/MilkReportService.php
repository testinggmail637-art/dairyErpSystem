<?php

namespace App\Services;

use App\Enums\AnimalType;
use App\Models\MilkProductionRecord;
use App\Reports\ReportTable;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Milk production reports (spec 7.3 / 7.6): daily animal × shift grid with totals by milk type, monthly day-by-day,
 * animal-wise for any period. Figures are SQL aggregates over the farm's milk entries joined to their animal (the milk
 * type is the animal's type); rows are never loaded into PHP.
 */
class MilkReportService
{
    public const REPORTS = ['daily', 'monthly', 'animals'];

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
