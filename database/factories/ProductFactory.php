<?php

namespace Database\Factories;

use App\Enums\MilkType;
use App\Models\Farm;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->unique()->bothify('Milk ??##'),
            'code' => fake()->unique()->bothify('P###'),
            'milk_type' => MilkType::Cow,
            'default_rate' => '60.00',
            'is_active' => true,
        ];
    }

    public function buffalo(): static
    {
        return $this->state(['milk_type' => MilkType::Buffalo, 'default_rate' => '70.00']);
    }
}
