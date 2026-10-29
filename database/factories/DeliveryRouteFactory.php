<?php

namespace Database\Factories;

use App\Models\DeliveryRoute;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRoute>
 */
class DeliveryRouteFactory extends Factory
{
    protected $model = DeliveryRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->unique()->bothify('Route ??##'),
            'delivery_user_id' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
