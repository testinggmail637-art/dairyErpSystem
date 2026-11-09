<?php

namespace Database\Factories;

use App\Enums\MilkType;
use App\Models\Farm;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    protected $model = Farmer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'code' => fake()->unique()->numerify('F-####'),
            'name' => fake()->name(),
            'village' => fake()->city(),
            'phone' => fake()->numerify('9#########'),
            'default_milk_type' => MilkType::Cow,
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }
}
