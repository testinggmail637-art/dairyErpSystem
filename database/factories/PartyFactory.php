<?php

namespace Database\Factories;

use App\Enums\PartyType;
use App\Models\Farm;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Party> */
class PartyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => fake()->unique()->name(),
            'phone' => (string) fake()->numberBetween(6000000000, 9999999999),
            'village' => fake()->city(),
            'party_type' => PartyType::Both,
            'opening_balance' => '0.00',
            'is_active' => true,
        ];
    }
}
