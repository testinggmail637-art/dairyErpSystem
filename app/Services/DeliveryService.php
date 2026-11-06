<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Marking deliveries (spec Section 6 delivery rules): delivered → stock out movement (type delivery), quantity
 * changes keep it in step; skipped → movement removed, delivered_qty = amount = 0.
 */
class DeliveryService
{
    public function __construct(private readonly MilkStockService $stock) {}

    /** Delivered with $qty litres (may be above planned). Throws InsufficientMilkStockException when stock is short. */
    public function markDelivered(Delivery $delivery, string $qty): Delivery
    {
        return DB::transaction(function () use ($delivery, $qty) {
            $qty = Money::round2($qty);

            $delivery->fill([
                'status' => DeliveryStatus::Delivered,
                'delivered_qty' => $qty,
                'amount' => Money::round2(Money::mul($qty, $delivery->rate)),
                'skip_reason' => null,
            ]);

            if ($delivery->isDirty('status') || $delivery->delivered_by === null) {
                $delivery->fill(['delivered_by' => auth()->id(), 'delivered_at' => now()]);
            }

            $delivery->save();
            $this->stock->syncForReference($delivery);

            return $delivery;
        });
    }

    public function skip(Delivery $delivery, string $reason): Delivery
    {
        return DB::transaction(function () use ($delivery, $reason) {
            $delivery->update([
                'status' => DeliveryStatus::Skipped,
                'delivered_qty' => '0.00',
                'amount' => '0.00',
                'skip_reason' => $reason,
                'delivered_by' => auth()->id(),
                'delivered_at' => now(),
            ]);
            $this->stock->syncForReference($delivery);

            return $delivery;
        });
    }

    /**
     * Admin grid bulk action: pending, unbilled rows (dated today or earlier) delivered with their planned litres.
     * All or nothing — one short stock rolls the whole batch back.
     *
     * @param  list<int>  $ids
     * @return int rows marked
     */
    public function markManyDelivered(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $rows = Delivery::query()->with('product:id,milk_type')
                ->whereKey($ids)
                ->where('status', DeliveryStatus::Pending)
                ->whereNull('bill_id')
                ->where('delivery_date', '<=', today()->toDateString())
                ->where('planned_qty', '>', 0)
                ->lockForUpdate()
                ->get();

            $rows->each(fn (Delivery $d) => $this->markDelivered($d, $d->planned_qty));

            return $rows->count();
        });
    }

    /**
     * Admin edit (reason comes from the request): status, litres, rate (editable until billed), notes.
     *
     * @param  array{status: string, delivered_qty?: ?string, rate: string, skip_reason?: ?string, notes?: ?string}  $data
     */
    public function update(Delivery $delivery, array $data): Delivery
    {
        return DB::transaction(function () use ($delivery, $data) {
            $status = DeliveryStatus::from($data['status']);
            $qty = $status === DeliveryStatus::Delivered ? Money::round2($data['delivered_qty']) : '0.00';

            $delivery->fill([
                'status' => $status,
                'rate' => Money::round2($data['rate']),
                'delivered_qty' => $qty,
                'amount' => Money::round2(Money::mul($qty, $data['rate'])),
                'skip_reason' => $status === DeliveryStatus::Skipped ? $data['skip_reason'] : null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($status === DeliveryStatus::Pending) {
                $delivery->fill(['delivered_by' => null, 'delivered_at' => null]);
            } elseif ($delivery->isDirty('status')) {
                $delivery->fill(['delivered_by' => auth()->id(), 'delivered_at' => now()]);
            }

            $delivery->save();
            $this->stock->syncForReference($delivery);

            return $delivery;
        });
    }
}
