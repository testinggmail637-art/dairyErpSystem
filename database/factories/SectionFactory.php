<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
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
