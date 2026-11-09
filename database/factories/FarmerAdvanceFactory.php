<?php

namespace Database\Factories;

use App\Models\Farm;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\PaymentMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmerAdvance>
 */
class FarmerAdvanceFactory extends Factory
{
    protected $model = FarmerAdvance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'farmer_id' => fn (array $a) => Farmer::factory()->create(['farm_id' => $a['farm_id']])->id,
            'advance_date' => today()->toDateString(),
            'amount' => '500.00',
            'payment_mode_id' => fn (array $a) => PaymentMode::factory()->create(['farm_id' => $a['farm_id']])->id,
        ];
    }
}
