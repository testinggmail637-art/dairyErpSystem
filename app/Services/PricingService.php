<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Selling rate per litre (spec Section 6): the customer's latest own rate with effective_from <= date,
 * else the product's default rate. Callers copy the result onto deliveries, so history never changes.
 */
class PricingService
{
    public function rateFor(Customer $customer, Product $product, CarbonInterface|string $date): string
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        $rate = CustomerRate::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->where('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->value('rate');

        return $rate ?? $product->default_rate;
    }

    /**
     * rateFor() for many customers at once (delivery sheet): one query for own rates + one for product defaults.
     *
     * @param  list<int>  $customerIds
     * @return array<string, string> "customerId:productId" => own rate; missing keys use defaultRates()
     */
    public function ownRatesFor(array $customerIds, string $date): array
    {
        if ($customerIds === []) {
            return [];
        }

        return DB::table('customer_rates as cr')
            ->whereIn('cr.customer_id', $customerIds)
            ->whereRaw('cr.effective_from = (select max(x.effective_from) from customer_rates x
                where x.customer_id = cr.customer_id and x.product_id = cr.product_id and x.effective_from <= ?)', [$date])
            ->get(['cr.customer_id', 'cr.product_id', 'cr.rate'])
            ->mapWithKeys(fn ($r) => ["{$r->customer_id}:{$r->product_id}" => $r->rate])
            ->all();
    }

    /** @return array<int, string> product id => default rate, deleted products included */
    public function defaultRates(int $farmId): array
    {
        return Product::allFarms()->withTrashed()->where('farm_id', $farmId)->pluck('default_rate', 'id')->all();
    }
}
