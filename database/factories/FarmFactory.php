<?php

namespace Database\Factories;

use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->lastName().' Dairy Farm',
            'code' => strtoupper(fake()->unique()->bothify('F??##')),
            'address' => fake()->city(),
            'phone' => (string) fake()->numberBetween(6000000000, 9999999999),
            'timezone' => 'Asia/Kolkata',
            'settings' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
