<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Product;
use Carbon\CarbonInterface;

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
}
