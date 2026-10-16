<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->unique()->company(),
            'phone' => (string) fake()->numberBetween(6000000000, 9999999999),
            'address' => fake()->city(),
            'gstin' => null,
            'opening_balance' => '0.00',
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
