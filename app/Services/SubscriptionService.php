<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Delivery;
use App\Models\Subscription;
use App\Models\SubscriptionPause;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Subscriptions and pauses (spec Section 6, Phase 3). Changes affect only delivery sheets generated afterwards. */
class SubscriptionService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): Subscription
    {
        return Subscription::create($this->attributes($data) + ['status' => SubscriptionStatus::Active]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($this->attributes($data) + ['status' => $data['status']]);

        return $subscription;
    }

    /**
     * Deletes the subscription with its pauses — only while it has no deliveries (then it is stopped instead).
     *
     * @throws DomainException
     */
    public function delete(Subscription $subscription): void
    {
        if (Delivery::query()->where('subscription_id', $subscription->id)->exists()) {
            throw new DomainException(__('This subscription already has deliveries, so it cannot be deleted. Set it to Stopped instead.'));
        }

        DB::transaction(function () use ($subscription) {
            $subscription->pauses()->delete();
            $subscription->delete();
        });
    }

    /** @param  array{from_date: string, to_date: string, reason?: string|null}  $data */
    public function addPause(Subscription $subscription, array $data): SubscriptionPause
    {
        return $subscription->pauses()->create([
            'farm_id' => $subscription->farm_id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'reason' => $data['reason'] ?? null,
        ]);
    }

    /**
     * Another active subscription of the same customer, product and shift whose period overlaps
     * start … end (open end = no end date). Deliveries are unique per customer × product × date × shift.
     */
    public function hasOverlap(int $customerId, int $productId, string $shift, string $start, ?string $end, ?int $ignoreId = null): bool
    {
        return Subscription::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('shift', $shift)
            ->where('status', SubscriptionStatus::Active)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->when($end, fn ($q) => $q->where('start_date', '<=', $end))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->exists();
    }

    public function pauseOverlaps(Subscription $subscription, string $from, string $to): bool
    {
        return $subscription->pauses()->where('from_date', '<=', $to)->where('to_date', '>=', $from)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'shift' => $data['shift'],
            'quantity' => Money::round2($data['quantity']),
            'stock_source' => $data['stock_source'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
