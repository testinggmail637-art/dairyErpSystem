<?php

namespace App\Services;

use App\Contracts\MovesMilkStock;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Exceptions\InsufficientMilkStockException;
use App\Models\Farm;
use App\Models\MilkStockMovement;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The milk ledger (spec Section 6, Phase 2). All stock changes go through here — no other code writes
 * milk_stock_movements. Stock is kept per source (own farm / collection) × milk type (cow / buffalo).
 */
class MilkStockService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** Milk into a stock (production, collection, adjustment in). */
    public function recordIn(
        MilkSource $source,
        MilkType $milkType,
        string $date,
        ?MilkShift $shift,
        MilkMovementType $type,
        mixed $quantity,
        ?Model $reference = null,
        ?string $reason = null,
    ): MilkStockMovement {
        $this->guardDirection($type, true);

        return $this->write($source, $milkType, $date, $shift, $type, $quantity, $reference, $reason);
    }

    /**
     * Milk out of a stock (delivery, wastage, home use, calf feeding, adjustment out). Refused with
     * InsufficientMilkStockException when the balance up to and including $date is too small, unless the farm
     * allows negative stock.
     */
    public function recordOut(
        MilkSource $source,
        MilkType $milkType,
        string $date,
        ?MilkShift $shift,
        MilkMovementType $type,
        mixed $quantity,
        ?Model $reference = null,
        ?string $reason = null,
    ): MilkStockMovement {
        $this->guardDirection($type, false);

        return $this->write($source, $milkType, $date, $shift, $type, $quantity, $reference, $reason);
    }

    /**
     * Keep the movement of a source record (production entry, collection, delivery) in step after it was created
     * or edited: creates, updates (litres, date, shift, milk type) or — at 0 litres — deletes it. Call inside the
     * transaction that saves the record.
     */
    public function syncForReference(Model&MovesMilkStock $reference, mixed $quantity = null): ?MilkStockMovement
    {
        $quantity = Money::round2($quantity ?? $reference->milkStockQuantity());
        $movement = $this->movementOf($reference);

        if (Money::compare($quantity, '0') <= 0) {
            $movement?->delete();

            return null;
        }

        ['source' => $source, 'milk_type' => $milkType, 'date' => $date, 'shift' => $shift, 'type' => $type] = $reference->milkStockTarget();

        if ($movement === null) {
            return $this->write($source, $milkType, $date, $shift, $type, $quantity, $reference, null);
        }

        return DB::transaction(function () use ($movement, $source, $milkType, $date, $shift, $type, $quantity) {
            if (! $type->isIn()) {
                $this->ensureAvailable($source, $milkType, $date, $quantity, $movement->id);
            }

            $movement->update([
                'movement_date' => $date,
                'shift' => $shift,
                'stock_source' => $source,
                'milk_type' => $milkType,
                'movement_type' => $type,
                'quantity_in' => $type->isIn() ? $quantity : '0',
                'quantity_out' => $type->isIn() ? '0' : $quantity,
            ]);

            return $movement;
        });
    }

    /** The source record is being deleted: its movement goes with it. Call inside the same transaction. */
    public function deleteForReference(Model $reference): void
    {
        $this->movementOf($reference)?->delete();
    }

    /** Σ in − Σ out of the current farm, optionally for one source / milk type and up to (including) a date. */
    public function balance(?MilkSource $source = null, ?MilkType $milkType = null, ?string $upToDate = null, ?int $exceptId = null): string
    {
        $row = MilkStockMovement::query()->toBase()
            ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0) AS balance')
            ->when($source, fn ($q) => $q->where('stock_source', $source->value))
            ->when($milkType, fn ($q) => $q->where('milk_type', $milkType->value))
            ->when($upToDate, fn ($q) => $q->where('movement_date', '<=', $upToDate))
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->first();

        return Money::round2((string) $row->balance);
    }

    /**
     * The day's stock per source × milk type plus the combined total (spec 7.4 daily stock sheet): two GROUP BY
     * queries, everything else is bcmath. `adjustment` is net (in − out); closing = opening + in − outs + adjustment.
     *
     * @return array{rows: array<string, array<string, mixed>>, combined: array<string, string>}
     */
    public function dailySummary(string $date): array
    {
        $date = Carbon::parse($date)->toDateString();
        $columns = ['opening', 'in', 'delivered', 'wastage', 'home_use', 'calf_feeding', 'adjustment', 'closing'];
        $zero = array_fill_keys($columns, '0.00');

        $rows = [];
        foreach (MilkSource::cases() as $source) {
            foreach (MilkType::cases() as $milkType) {
                $rows[$source->value.'.'.$milkType->value] = ['source' => $source, 'milk_type' => $milkType] + $zero;
            }
        }

        MilkStockMovement::query()->toBase()
            ->select(['stock_source', 'milk_type'])
            ->selectRaw('SUM(quantity_in) - SUM(quantity_out) AS balance')
            ->where('movement_date', '<', $date)
            ->groupBy('stock_source', 'milk_type')
            ->get()
            ->each(function ($row) use (&$rows) {
                $rows[$row->stock_source.'.'.$row->milk_type]['opening'] = Money::round2((string) $row->balance);
            });

        MilkStockMovement::query()->toBase()
            ->select(['stock_source', 'milk_type', 'movement_type'])
            ->selectRaw('SUM(quantity_in) AS qty_in, SUM(quantity_out) AS qty_out')
            ->where('movement_date', $date)
            ->groupBy('stock_source', 'milk_type', 'movement_type')
            ->get()
            ->each(function ($row) use (&$rows) {
                $key = $row->stock_source.'.'.$row->milk_type;
                $column = match (MilkMovementType::from($row->movement_type)) {
                    MilkMovementType::Production, MilkMovementType::Collection => 'in',
                    MilkMovementType::Delivery => 'delivered',
                    MilkMovementType::Wastage => 'wastage',
                    MilkMovementType::HomeUse => 'home_use',
                    MilkMovementType::CalfFeeding => 'calf_feeding',
                    MilkMovementType::AdjustmentIn, MilkMovementType::AdjustmentOut => 'adjustment',
                };
                $qty = $column === 'adjustment'
                    ? Money::sub((string) $row->qty_in, (string) $row->qty_out)
                    : Money::add((string) $row->qty_in, (string) $row->qty_out);
                $rows[$key][$column] = Money::add($rows[$key][$column], $qty);
            });

        $combined = $zero;
        foreach ($rows as $key => $row) {
            $rows[$key]['closing'] = Money::sub(
                Money::add($row['opening'], $row['in'], $row['adjustment']),
                Money::add($row['delivered'], $row['wastage'], $row['home_use'], $row['calf_feeding']),
            );
            foreach ($columns as $column) {
                $combined[$column] = Money::add($combined[$column], $rows[$key][$column]);
            }
        }

        return ['rows' => $rows, 'combined' => $combined];
    }

    private function write(
        MilkSource $source,
        MilkType $milkType,
        string $date,
        ?MilkShift $shift,
        MilkMovementType $type,
        mixed $quantity,
        ?Model $reference,
        ?string $reason,
    ): MilkStockMovement {
        $quantity = Money::round2($quantity);
        $reason = filled($reason) ? trim($reason) : null;

        if (Money::compare($quantity, '0') <= 0) {
            throw new InvalidArgumentException('A milk stock movement needs a quantity above zero.');
        }
        if ($type->requiresReason() && $reason === null) {
            throw new InvalidArgumentException("A {$type->value} movement needs a reason.");
        }

        return DB::transaction(function () use ($source, $milkType, $date, $shift, $type, $quantity, $reference, $reason) {
            if (! $type->isIn()) {
                $this->ensureAvailable($source, $milkType, $date, $quantity);
            }

            return MilkStockMovement::create([
                'farm_id' => $reference?->getAttribute('farm_id'),
                'movement_date' => $date,
                'shift' => $shift,
                'stock_source' => $source,
                'milk_type' => $milkType,
                'movement_type' => $type,
                'quantity_in' => $type->isIn() ? $quantity : '0',
                'quantity_out' => $type->isIn() ? '0' : $quantity,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Balance check for outgoing milk (spec: up to and including the movement date, all shifts). The farm row is
     * locked first so two outgoing movements of the same farm cannot both pass on the same litres.
     */
    private function ensureAvailable(MilkSource $source, MilkType $milkType, string $date, string $quantity, ?int $exceptId = null): void
    {
        $farm = Farm::query()->whereKey($this->currentFarm->id())->lockForUpdate()->first();

        if ($farm === null || $farm->setting('allow_negative_milk_stock')) {
            return;
        }

        $available = $this->balance($source, $milkType, $date, $exceptId);

        if (Money::compare($quantity, $available) > 0) {
            throw new InsufficientMilkStockException($source, $milkType, Carbon::parse($date)->toDateString(), $available, $quantity);
        }
    }

    private function guardDirection(MilkMovementType $type, bool $in): void
    {
        if ($type->isIn() !== $in) {
            throw new InvalidArgumentException("{$type->value} is not an ".($in ? 'incoming' : 'outgoing').' movement.');
        }
    }

    private function movementOf(Model $reference): ?MilkStockMovement
    {
        return MilkStockMovement::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->first();
    }
}
