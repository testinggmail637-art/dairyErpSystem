<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->name(),
            'phone' => null,
            'designation' => fake()->randomElement(['Milker', 'Helper', 'Driver', 'Supervisor']),
            'monthly_salary' => (string) fake()->numberBetween(8, 25).'000.00',
            'joining_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'leaving_date' => null,
            'is_active' => true,
        ];
    }
}
