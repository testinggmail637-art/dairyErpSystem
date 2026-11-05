<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Farm;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery sheet generation (spec Section 6, Phase 3): a `pending` delivery for every active subscription valid on
 * the date and not paused, for active customers. Idempotent — rows that exist (any status) are never touched.
 * Works on an explicit farm, so the console command and the web button share it.
 */
class DeliverySheetService
{
    public function __construct(private readonly PricingService $pricing) {}

    /** @return int number of new pending deliveries */
    public function generate(Farm $farm, string $date): int
    {
        $defaults = $this->pricing->defaultRates($farm->id);
        $userId = auth()->id();
        $now = now();
        $created = 0;

        Subscription::allFarms()
            ->join('customers', 'customers.id', '=', 'subscriptions.customer_id')
            ->where('subscriptions.farm_id', $farm->id)
            ->where('subscriptions.status', SubscriptionStatus::Active)
            ->where('subscriptions.start_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('subscriptions.end_date')->orWhere('subscriptions.end_date', '>=', $date))
            ->whereNotExists(fn ($q) => $q->from('subscription_pauses as p')
                ->whereColumn('p.subscription_id', 'subscriptions.id')
                ->where('p.from_date', '<=', $date)->where('p.to_date', '>=', $date))
            ->where('customers.is_active', true)
            ->whereNull('customers.deleted_at')
            ->select(['subscriptions.id', 'subscriptions.customer_id', 'subscriptions.product_id', 'subscriptions.shift',
                'subscriptions.quantity', 'subscriptions.stock_source', 'customers.route_id'])
            ->chunkById(500, function (Collection $subs) use ($farm, $date, $defaults, $userId, $now, &$created) {
                $own = $this->pricing->ownRatesFor($subs->pluck('customer_id')->unique()->values()->all(), $date);

                $rows = $subs->map(fn (Subscription $s) => [
                    'farm_id' => $farm->id,
                    'delivery_date' => $date,
                    'shift' => $s->shift->value,
                    'customer_id' => $s->customer_id,
                    'product_id' => $s->product_id,
                    'route_id' => $s->getAttribute('route_id'),
                    'subscription_id' => $s->id,
                    'stock_source' => $s->stock_source->value,
                    'planned_qty' => $s->quantity,
                    'delivered_qty' => '0.00',
                    'rate' => $own["{$s->customer_id}:{$s->product_id}"] ?? $defaults[$s->product_id],
                    'amount' => '0.00',
                    'status' => DeliveryStatus::Pending->value,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                // The unique (customer, product, date, shift) key skips rows that already exist.
                $created += DB::table('deliveries')->insertOrIgnore($rows);
            }, 'subscriptions.id', 'id');

        return $created;
    }
}
