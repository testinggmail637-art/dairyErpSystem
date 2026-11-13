<?php

namespace Database\Factories;

use App\Models\Animal;
use App\Models\Farm;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'animal_id' => fn (array $a) => Animal::factory()->create(['farm_id' => $a['farm_id']])->id,
            'treatment_date' => today()->toDateString(),
            'diagnosis' => 'Mastitis',
            'cost' => '0.00',
        ];
    }
}
