<?php

namespace App\Services;

use App\Enums\MilkShift;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Exceptions\BelowStandardMilkException;
use App\Models\Farmer;
use App\Models\MilkCollection;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Milk collection from farmers (spec Phase 4). The rate comes from the farmer's chart (RateChartService) and is copied
 * onto the row with the amount; accepted milk moves the collection stock (MilkStockService) in the same transaction.
 */
class CollectionService
{
    public function __construct(
        private readonly RateChartService $charts,
        private readonly MilkStockService $stock,
    ) {}

    /**
     * Rate and amount for a reading, without saving (live preview on the entry screen).
     *
     * @return array{chart_id: int, chart: string, rate: string, amount: string, below_standard: bool}
     *
     * @throws DomainException no chart in force, or the chart needs a missing reading / has no matching slab
     */
    public function quote(Farmer $farmer, MilkType $milkType, string $date, string $qty, ?string $fat, ?string $snf): array
    {
        $chart = $this->charts->resolve($farmer, $milkType, $date)
            ?? throw new DomainException(__('There is no rate chart for :milk milk on :date. Add one in Rate Charts.', [
                'milk' => mb_strtolower($milkType->label()), 'date' => fmt_date($date),
            ]));
        $result = $this->charts->calculate($chart->loadMissing('slabs'), $fat, $snf);

        return ['chart_id' => $chart->id, 'chart' => $chart->name, 'rate' => $result['rate'],
            'amount' => Money::mul($qty, $result['rate']), 'below_standard' => $result['below_standard']];
    }

    /**
     * Save one collection; idempotent on client_uuid (spec 4.7).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: MilkCollection, 1: bool} the row and whether it was newly created
     *
     * @throws BelowStandardMilkException when the reading is below standard and accept_below is not set
     */
    public function create(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;
        if ($uuid && ($existing = MilkCollection::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        $farmer = Farmer::query()->findOrFail($data['farmer_id']);
        $milkType = MilkType::from($data['milk_type']);
        $shift = MilkShift::from($data['shift']);
        $this->ensureFirstOfShift($farmer, $data['collection_date'], $shift, $milkType);

        $qty = Money::round2($data['quantity_litre']);
        $accepted = ($data['quality_status'] ?? QualityStatus::Accepted->value) === QualityStatus::Accepted->value;
        $price = ['chart_id' => null, 'rate' => '0.00', 'amount' => '0.00'];
        if ($accepted) {
            $price = $this->quote($farmer, $milkType, $data['collection_date'], $qty, $data['fat'] ?? null, $data['snf'] ?? null);
            if ($price['below_standard'] && empty($data['accept_below'])) {
                throw new BelowStandardMilkException($price['rate'], $price['amount']);
            }
        }

        try {
            return [DB::transaction(function () use ($data, $farmer, $milkType, $shift, $qty, $accepted, $price, $uuid) {
                $collection = MilkCollection::create([
                    'collection_date' => $data['collection_date'], 'shift' => $shift, 'farmer_id' => $farmer->id, 'milk_type' => $milkType,
                    'quantity_litre' => $qty, 'fat' => $data['fat'] ?? null, 'snf' => $data['snf'] ?? null, 'clr' => $data['clr'] ?? null,
                    'rate_chart_id' => $price['chart_id'], 'rate_per_litre' => $price['rate'], 'amount' => $price['amount'],
                    'quality_status' => $accepted ? QualityStatus::Accepted : QualityStatus::Rejected,
                    'rejection_reason' => $accepted ? null : ($data['rejection_reason'] ?? null),
                    'client_uuid' => $uuid, 'notes' => $data['notes'] ?? null,
                ]);
                $this->stock->syncForReference($collection);

                return $collection;
            }), true];
        } catch (UniqueConstraintViolationException $e) {
            // a double submit raced past the checks: the unique index kept the first one
            $existing = $uuid ? MilkCollection::query()->where('client_uuid', $uuid)->first() : null;
            if ($existing) {
                return [$existing, false];
            }
            $this->ensureFirstOfShift($farmer, $data['collection_date'], $shift, $milkType);

            throw $e;
        }
    }

    /**
     * Entries of one date and shift, newest first.
     *
     * @return Collection<int, MilkCollection>
     */
    public function entries(string $date, MilkShift $shift): Collection
    {
        return MilkCollection::query()->with('farmer:id,code,name')
            ->where('collection_date', $date)->where('shift', $shift)
            ->latest('id')->get();
    }

    /**
     * Totals of one date and shift in one query: accepted litres, average fat / SNF weighted by litres, amount.
     *
     * @return array{count: int, litres: string, rejected: string, fat: ?string, snf: ?string, amount: string}
     */
    public function shiftTotals(string $date, MilkShift $shift): array
    {
        $accepted = QualityStatus::Accepted->value;
        $row = MilkCollection::query()->where('collection_date', $date)->where('shift', $shift)
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('COALESCE(SUM(CASE WHEN quality_status = ? THEN quantity_litre END), 0) AS litres', [$accepted])
            ->selectRaw('COALESCE(SUM(CASE WHEN quality_status <> ? THEN quantity_litre END), 0) AS rejected', [$accepted])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND fat IS NOT NULL THEN fat * quantity_litre END) AS fat_l', [$accepted])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND fat IS NOT NULL THEN quantity_litre END) AS fat_q', [$accepted])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND snf IS NOT NULL THEN snf * quantity_litre END) AS snf_l', [$accepted])
            ->selectRaw('SUM(CASE WHEN quality_status = ? AND snf IS NOT NULL THEN quantity_litre END) AS snf_q', [$accepted])
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount')
            ->toBase()->first();

        $avg = fn ($sum, $qty) => $qty !== null && Money::compare((string) $qty, '0') > 0 ? Money::div((string) $sum, (string) $qty) : null;

        return [
            'count' => (int) $row->cnt,
            'litres' => Money::round2((string) $row->litres),
            'rejected' => Money::round2((string) $row->rejected),
            'fat' => $avg($row->fat_l, $row->fat_q),
            'snf' => $avg($row->snf_l, $row->snf_q),
            'amount' => Money::round2((string) $row->amount),
        ];
    }

    private function ensureFirstOfShift(Farmer $farmer, string $date, MilkShift $shift, MilkType $milkType): void
    {
        $taken = MilkCollection::query()->where('farmer_id', $farmer->id)->where('collection_date', $date)
            ->where('shift', $shift)->where('milk_type', $milkType)->exists();

        if ($taken) {
            throw new DomainException(__(':code :name already has :milk milk in the :date :shift shift. Change that entry instead.', [
                'code' => $farmer->code, 'name' => $farmer->name, 'milk' => mb_strtolower($milkType->label()),
                'date' => fmt_date($date), 'shift' => mb_strtolower($shift->label()),
            ]));
        }
    }
}
