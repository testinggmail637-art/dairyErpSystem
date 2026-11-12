<?php

namespace App\Services;

use App\Enums\MilkShift;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Models\MilkCollection;
use App\Reports\ReportTable;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Milk collection reports (spec 7.6 item 2): daily shift summary, farmer-wise (both with average fat / SNF) and
 * rejected milk. Sums in SQL on the (farm_id, collection_date, …) indexes. Litres, amount and fat / SNF count
 * accepted milk only; fat / SNF are averaged by litres over the entries that have a reading.
 */
class CollectionReportService
{
    public const REPORTS = ['daily', 'farmers', 'rejected'];

    public function dailyShift(string $from, string $to): ReportTable
    {
        $lines = $this->summary($this->between($from, $to))
            ->groupBy('collection_date', 'shift', 'milk_type')
            ->addSelect(['collection_date', 'shift', 'milk_type'])
            ->selectRaw('COUNT(DISTINCT farmer_id) AS farmers')
            ->orderBy('collection_date')->orderByRaw('shift = ?', [MilkShift::Evening->value])->orderBy('milk_type')
            ->get();

        return new ReportTable(
            __('Daily collection by shift'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Shift')),
                ReportTable::column(__('Milk')),
                ReportTable::column(__('Farmers'), ReportTable::NUMBER),
                ...$this->summaryColumns(),
            ],
            $lines->map(fn ($l) => [$l->collection_date, MilkShift::from($l->shift)->label(), MilkType::from($l->milk_type)->label(),
                (int) $l->farmers, ...$this->summaryCells($l)])->all(),
            [__('Total'), '', '', '', ...$this->summaryTotals($lines)],
            [$this->note()],
            emptyText: __('No milk collected in this period.'),
        );
    }

    public function farmerWise(string $from, string $to): ReportTable
    {
        $lines = $this->summary($this->between($from, $to)->join('farmers', 'farmers.id', '=', 'milk_collections.farmer_id'))
            ->groupBy('farmers.id', 'farmers.code', 'farmers.name', 'milk_type')
            ->addSelect(['farmers.code', 'farmers.name', 'milk_type'])
            ->selectRaw('COUNT(*) AS entries')
            ->orderBy('farmers.code')->orderBy('milk_type')
            ->get();

        return new ReportTable(
            __('Farmer-wise collection'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Code')),
                ReportTable::column(__('Farmer')),
                ReportTable::column(__('Milk')),
                ReportTable::column(__('Entries'), ReportTable::NUMBER),
                ...$this->summaryColumns(),
            ],
            $lines->map(fn ($l) => [$l->code, $l->name, MilkType::from($l->milk_type)->label(), (int) $l->entries, ...$this->summaryCells($l)])->all(),
            [__('Total (:count)', ['count' => $lines->pluck('code')->unique()->count()]), '', '', (int) $lines->sum('entries'), ...$this->summaryTotals($lines)],
            [$this->note()],
            emptyText: __('No milk collected in this period.'),
        );
    }

    public function rejected(string $from, string $to): ReportTable
    {
        $lines = $this->between($from, $to)
            ->join('farmers', 'farmers.id', '=', 'milk_collections.farmer_id')
            ->where('quality_status', QualityStatus::Rejected->value)
            ->select(['collection_date', 'shift', 'farmers.code', 'farmers.name', 'milk_type', 'quantity_litre', 'fat', 'snf', 'rejection_reason'])
            ->orderBy('collection_date')->orderByRaw('shift = ?', [MilkShift::Evening->value])->orderBy('farmers.code')
            ->get();

        return new ReportTable(
            __('Rejected milk'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Shift')),
                ReportTable::column(__('Code')),
                ReportTable::column(__('Farmer')),
                ReportTable::column(__('Milk')),
                ReportTable::column(__('Litres'), ReportTable::QUANTITY),
                ReportTable::column(__('Fat'), ReportTable::QUANTITY),
                ReportTable::column(__('SNF'), ReportTable::QUANTITY),
                ReportTable::column(__('Reason')),
            ],
            $lines->map(fn ($l) => [$l->collection_date, MilkShift::from($l->shift)->label(), $l->code, $l->name,
                MilkType::from($l->milk_type)->label(), $l->quantity_litre, $l->fat, $l->snf, $l->rejection_reason])->all(),
            [__('Total (:count)', ['count' => $lines->count()]), '', '', '', '', Money::sum($lines->pluck('quantity_litre')), null, null, ''],
            emptyText: __('No rejected milk in this period.'),
        );
    }

    /** The farm's collections in the period (current-farm scope via the model). */
    private function between(string $from, string $to): Builder
    {
        return MilkCollection::query()->toBase()->whereBetween('milk_collections.collection_date', [$from, $to]);
    }

    /** Accepted litres / amount, litre-weighted fat & SNF parts, rejected litres. */
    private function summary(Builder $query): Builder
    {
        $ok = QualityStatus::Accepted->value;

        return $query
            ->selectRaw('SUM(CASE WHEN quality_status = ? THEN quantity_litre ELSE 0 END) AS qty', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? THEN amount ELSE 0 END) AS amount', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND fat IS NOT NULL THEN fat * quantity_litre ELSE 0 END) AS fat_l', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND fat IS NOT NULL THEN quantity_litre ELSE 0 END) AS fat_qty', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND snf IS NOT NULL THEN snf * quantity_litre ELSE 0 END) AS snf_l', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND snf IS NOT NULL THEN quantity_litre ELSE 0 END) AS snf_qty', [$ok])
            ->selectRaw('SUM(CASE WHEN quality_status = ? THEN quantity_litre ELSE 0 END) AS rejected_qty', [QualityStatus::Rejected->value]);
    }

    /** @return list<array{label: string, type: string}> */
    private function summaryColumns(): array
    {
        return [
            ReportTable::column(__('Litres'), ReportTable::QUANTITY),
            ReportTable::column(__('Avg fat'), ReportTable::QUANTITY),
            ReportTable::column(__('Avg SNF'), ReportTable::QUANTITY),
            ReportTable::column(__('Amount'), ReportTable::MONEY),
            ReportTable::column(__('Avg rate / L'), ReportTable::MONEY),
            ReportTable::column(__('Rejected L'), ReportTable::QUANTITY),
        ];
    }

    /** @return list<?string> */
    private function summaryCells(object $l): array
    {
        return [Money::round2((string) $l->qty), $this->avg($l->fat_l, $l->fat_qty), $this->avg($l->snf_l, $l->snf_qty),
            Money::round2((string) $l->amount), $this->avg($l->amount, $l->qty), Money::round2((string) $l->rejected_qty)];
    }

    /** @return list<?string> */
    private function summaryTotals(Collection $lines): array
    {
        $sum = fn (string $field) => Money::sum($lines->pluck($field)->map(fn ($v) => (string) $v));

        return [$sum('qty'), $this->avg($sum('fat_l'), $sum('fat_qty')), $this->avg($sum('snf_l'), $sum('snf_qty')),
            $sum('amount'), $this->avg($sum('amount'), $sum('qty')), $sum('rejected_qty')];
    }

    private function avg(mixed $part, mixed $qty): ?string
    {
        return Money::isZero((string) $qty) ? null : Money::round2(Money::div((string) $part, (string) $qty));
    }

    private function note(): string
    {
        return __('Litres, amount and fat / SNF are accepted milk only; fat / SNF are averaged by litres. Rejected milk is shown apart.');
    }

    private function period(string $from, string $to): string
    {
        return Format::date($from).' – '.Format::date($to);
    }
}
