<?php

namespace Database\Factories;

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Models\Farm;
use App\Models\RateChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateChart>
 */
class RateChartFactory extends Factory
{
    protected $model = RateChart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'name' => 'Cow fixed',
            'milk_type' => MilkType::Cow,
            'method' => RateMethod::Fixed,
            'fixed_rate' => '40.00',
            'effective_from' => '2026-01-01',
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
