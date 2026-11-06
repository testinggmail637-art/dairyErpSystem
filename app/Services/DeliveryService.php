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
}
