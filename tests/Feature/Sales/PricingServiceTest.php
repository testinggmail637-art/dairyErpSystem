<?php

use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Farm;
use App\Models\Product;
use App\Services\PricingService;
use App\Support\CurrentFarm;

beforeEach(function () {
    $this->farm = Farm::factory()->create();
    app(CurrentFarm::class)->set($this->farm);
    $this->customer = Customer::factory()->create(['farm_id' => $this->farm->id]);
    $this->product = Product::factory()->create(['farm_id' => $this->farm->id, 'default_rate' => '60.00']);
});

it('uses the latest own rate on or before the date, else the product default', function () {
    $other = Product::factory()->create(['farm_id' => $this->farm->id, 'default_rate' => '70.00']);
    $rate = fn (string $from, string $value, ?Product $p = null) => CustomerRate::factory()->create([
        'customer_id' => $this->customer->id, 'product_id' => ($p ?? $this->product)->id, 'rate' => $value, 'effective_from' => $from,
    ]);
    $rate('2026-04-01', '62.00');
    $rate('2026-07-01', '64.00');
    $rate('2026-01-01', '99.00', $other); // other product never leaks in

    $pricing = app(PricingService::class);

    expect($pricing->rateFor($this->customer, $this->product, '2026-03-31'))->toBe('60.00')
        ->and($pricing->rateFor($this->customer, $this->product, '2026-04-01'))->toBe('62.00')
        ->and($pricing->rateFor($this->customer, $this->product, '2026-06-30'))->toBe('62.00')
        ->and($pricing->rateFor($this->customer, $this->product, now()->setDate(2026, 7, 1)))->toBe('64.00');

    // Another customer gets the default.
    $someoneElse = Customer::factory()->create(['farm_id' => $this->farm->id]);
    expect($pricing->rateFor($someoneElse, $this->product, '2026-08-01'))->toBe('60.00');
});
