<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerRate>
 */
class CustomerRateFactory extends Factory
{
    protected $model = CustomerRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'farm_id' => fn (array $a) => Customer::allFarms()->findOrFail($a['customer_id'])->farm_id,
            'product_id' => fn (array $a) => Product::factory()->create(['farm_id' => $a['farm_id']])->id,
            'rate' => '65.00',
            'effective_from' => now()->toDateString(),
        ];
    }
}
