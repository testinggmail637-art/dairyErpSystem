<?php

namespace App\Services;

use App\Contracts\MovesMilkStock;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Exceptions\InsufficientMilkStockException;
use App\Models\Farm;
use App\Models\MilkProductionRecord;
use App\Models\MilkStockMovement;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The milk ledger (spec Section 6, Phase 2). All stock changes go through here — no other code writes
 * milk_stock_movements. Stock is kept per source (own farm / collection) × milk type (cow / buffalo).
 */
class MilkStockService
{
    public const LEDGER_PER_PAGE = 100;

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

    /**
     * Usage (wastage / home use / calf feeding) or adjustment from the milk stock forms (spec 7.4); idempotent on
     * client_uuid (spec 4.7).
     *
     * @param  array{stock_source: string, milk_type: string, movement_date: string, shift?: ?string, movement_type: string, quantity: string, reason: string, client_uuid?: ?string}  $data
     * @return array{0: MilkStockMovement, 1: bool} the movement and whether it was newly created
     */
    public function recordManual(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = MilkStockMovement::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        $type = MilkMovementType::from($data['movement_type']);
        $movement = $this->write(
            MilkSource::from($data['stock_source']),
            MilkType::from($data['milk_type']),
            $data['movement_date'],
            MilkShift::tryFrom((string) ($data['shift'] ?? '')),
            $type,
            $data['quantity'],
            null,
            $data['reason'],
            $uuid,
        );

        return [$movement, true];
    }

    /**
     * Ledger with a running balance (spec 7.4), oldest first like a passbook. The running balance follows the
     * source / milk type filter (both empty = all milk) and starts from the balance before $from; the movement
     * type filter only hides rows, so the balance column stays true. Sums and the window run on lean rows; the page's
     * rows are then loaded as models with their creator and source record.
     *
     * @param  array{from: string, to: string, source: string, milk_type: string, movement_type: string}  $filters
     * @return array{opening: string, in: string, out: string, closing: string, rows: LengthAwarePaginator}
     */
    public function ledger(array $filters, int $page = 1, array $query = []): array
    {
        $source = MilkSource::tryFrom($filters['source']);
        $milkType = MilkType::tryFrom($filters['milk_type']);
        $opening = $this->balance($source, $milkType, Carbon::parse($filters['from'])->subDay()->toDateString());

        $period = fn () => MilkStockMovement::query()->toBase()
            ->when($source, fn ($q) => $q->where('stock_source', $source->value))
            ->when($milkType, fn ($q) => $q->where('milk_type', $milkType->value))
            ->whereBetween('movement_date', [$filters['from'], $filters['to']]);

        // totals need no running balance: a plain aggregate
        $totals = $period()
            ->when($filters['movement_type'], fn ($q, $v) => $q->where('movement_type', $v))
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(quantity_in), 0) AS qty_in, COALESCE(SUM(quantity_out), 0) AS qty_out')
            ->first();

        $lean = $period()
            ->select(['id', 'movement_date', 'movement_type'])
            // CAST keeps it exact: a bound string added to a DECIMAL would otherwise become a DOUBLE
            ->selectRaw('CAST(? AS DECIMAL(14,2)) + SUM(quantity_in - quantity_out) OVER (ORDER BY movement_date, id ROWS UNBOUNDED PRECEDING) AS balance', [$opening]);
        $rows = DB::query()->fromSub($lean, 'm')
            ->when($filters['movement_type'], fn ($q, $v) => $q->where('movement_type', $v))
            ->select(['id', 'balance'])->orderBy('movement_date')->orderBy('id')
            ->forPage($page, self::LEDGER_PER_PAGE)
            ->get();

        $models = MilkStockMovement::query()
            ->with(['creator:id,name', 'reference' => fn (MorphTo $m) => $m->morphWith([MilkProductionRecord::class => ['animal:id,tag_no,name']])])
            ->findMany($rows->pluck('id'))
            ->keyBy('id');
        $items = $rows->map(fn ($row) => $models->get($row->id)->setAttribute('balance', Money::round2((string) $row->balance)));

        $in = Money::round2((string) $totals->qty_in);
        $out = Money::round2((string) $totals->qty_out);
        $closing = $filters['movement_type'] === ''
            ? Money::sub(Money::add($opening, $in), $out)
            : $this->balance($source, $milkType, $filters['to']);

        return [
            'opening' => $opening,
            'in' => $in,
            'out' => $out,
            'closing' => $closing,
            'rows' => new LengthAwarePaginator($items, (int) $totals->cnt, self::LEDGER_PER_PAGE, $page, [
                'path' => request()->url(),
                'query' => $query,
            ]),
        ];
    }

    /**
     * The day's usage and adjustments (manual movements) for the stock sheet, with who entered them.
     *
     * @return Collection<int, MilkStockMovement>
     */
    public function manualMovementsOn(string $date): Collection
    {
        $types = array_map(fn (MilkMovementType $t) => $t->value, array_filter(MilkMovementType::cases(), fn ($t) => $t->requiresReason()));

        return MilkStockMovement::query()
            ->with('creator:id,name')
            ->where('movement_date', $date)
            ->whereIn('movement_type', $types)
            ->orderBy('id')
            ->get();
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
        ?string $clientUuid = null,
    ): MilkStockMovement {
        $quantity = Money::round2($quantity);
        $reason = filled($reason) ? trim($reason) : null;

        if (Money::compare($quantity, '0') <= 0) {
            throw new InvalidArgumentException('A milk stock movement needs a quantity above zero.');
        }
        if ($type->requiresReason() && $reason === null) {
            throw new InvalidArgumentException("A {$type->value} movement needs a reason.");
        }

        return DB::transaction(function () use ($source, $milkType, $date, $shift, $type, $quantity, $reference, $reason, $clientUuid) {
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
                'client_uuid' => $clientUuid,
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
