<?php

namespace Database\Factories;

use App\Enums\AnimalType;
use App\Models\AnimalBreed;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimalBreed>
 */
class AnimalBreedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'animal_type' => AnimalType::Cow,
            'name' => fake()->unique()->bothify('Breed ??##'),
            'is_active' => true,
        ];
    }

    public function buffalo(): static
    {
        return $this->state(['animal_type' => AnimalType::Buffalo]);
    }
}
