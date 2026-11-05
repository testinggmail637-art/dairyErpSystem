<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => fake()->unique()->numerify('C-#####'),
            'name' => fake()->name(),
            'customer_type' => CustomerType::B2c,
            'phone' => fake()->numerify('9#########'),
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }
}
