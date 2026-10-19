<?php

namespace Database\Factories;

use App\Enums\AnimalGender;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\AnimalType;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Models\Animal;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A milking cow of the herd by default; ->buffalo(), ->male(), ->stage(), ->status() for others.
 *
 * @extends Factory<Animal>
 */
class AnimalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'tag_no' => fake()->unique()->bothify('C-####'),
            'name' => fake()->optional()->firstNameFemale(),
            'animal_type' => AnimalType::Cow,
            'breed_id' => null,
            'gender' => AnimalGender::Female,
            'date_of_birth' => fake()->dateTimeBetween('-8 years', '-3 years')->format('Y-m-d'),
            'dob_is_estimated' => false,
            'source' => AnimalSource::Opening,
            'ownership_type' => OwnershipType::OwnHerd,
            'lifecycle_stage' => LifecycleStage::Milking,
            'status' => AnimalStatus::Active,
            'lactation_number' => 1,
        ];
    }

    public function buffalo(): static
    {
        return $this->state(fn () => ['animal_type' => AnimalType::Buffalo, 'tag_no' => fake()->unique()->bothify('B-####')]);
    }

    public function male(): static
    {
        return $this->state(['gender' => AnimalGender::Male, 'lifecycle_stage' => LifecycleStage::Bull, 'lactation_number' => 0]);
    }

    public function stage(LifecycleStage $stage): static
    {
        return $this->state(['lifecycle_stage' => $stage]);
    }

    public function status(AnimalStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
