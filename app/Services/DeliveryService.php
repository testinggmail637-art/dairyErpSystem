<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Marking deliveries (spec Section 6 delivery rules): delivered → stock out movement (type delivery), quantity
 * changes keep it in step; skipped → movement removed, delivered_qty = amount = 0.
 */
class DeliveryService
{
    public function __construct(private readonly MilkStockService $stock, private readonly PricingService $pricing) {}

    /**
     * One-off / B2B dispatch (spec Section 6): a delivery created directly as delivered, no subscription, planned 0.
     * Empty rate → PricingService. Idempotent on client_uuid (spec 4.7).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Delivery, 1: bool} the delivery and whether it was newly created
     */
    public function dispatch(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = Delivery::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        return [DB::transaction(function () use ($data, $uuid) {
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $product = Product::query()->findOrFail($data['product_id']);
            $rate = Money::round2($data['rate'] ?? $this->pricing->rateFor($customer, $product, $data['delivery_date']));
            $qty = Money::round2($data['delivered_qty']);

            $delivery = Delivery::create([
                'delivery_date' => $data['delivery_date'],
                'shift' => $data['shift'],
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'route_id' => $customer->route_id,
                'stock_source' => $data['stock_source'],
                'planned_qty' => '0.00',
                'delivered_qty' => $qty,
                'rate' => $rate,
                'amount' => Money::round2(Money::mul($qty, $rate)),
                'status' => DeliveryStatus::Delivered,
                'delivered_by' => auth()->id(),
                'delivered_at' => now(),
                'client_uuid' => $uuid,
            ] + $this->readings($data) + ['notes' => $data['notes'] ?? null]);

            $delivery->setRelation('product', $product);
            $this->stock->syncForReference($delivery);

            return $delivery;
        }), true];
    }

    /**
     * Our fat / SNF and the buyer's measured litres / fat / SNF (dairy plant reconciliation), all optional.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, ?string>
     */
    public function readings(array $data): array
    {
        return collect(Delivery::READINGS)
            ->mapWithKeys(fn ($k) => [$k => isset($data[$k]) ? Money::round2($data[$k]) : null])
            ->all();
    }

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
            ] + $this->readings($data));

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
