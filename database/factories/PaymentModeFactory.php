<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\PaymentMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMode>
 */
class PaymentModeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'code' => strtoupper(fake()->unique()->lexify('???###')),
            'is_active' => true,
        ];
    }
}
