<?php

namespace Database\Factories;

use App\Enums\MilkShift;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Pass an animal: MilkProductionRecord::factory()->for($animal)->create([...]) — farm_id follows the animal.
 *
 * @extends Factory<MilkProductionRecord>
 */
class MilkProductionRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'animal_id' => Animal::factory(),
            'farm_id' => fn (array $a) => Animal::allFarms()->withTrashed()->find($a['animal_id'])->farm_id,
            'production_date' => today()->toDateString(),
            'shift' => MilkShift::Morning,
            'quantity_litre' => sprintf('%d.%d0', fake()->numberBetween(3, 12), fake()->numberBetween(0, 9)),
        ];
    }
}
