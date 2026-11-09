<?php

namespace Database\Factories;

use App\Enums\MilkShift;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Models\Farm;
use App\Models\Farmer;
use App\Models\MilkCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MilkCollection>
 */
class MilkCollectionFactory extends Factory
{
    protected $model = MilkCollection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'collection_date' => today()->toDateString(),
            'shift' => MilkShift::Morning,
            'farmer_id' => fn (array $a) => Farmer::factory()->create(['farm_id' => $a['farm_id']])->id,
            'milk_type' => MilkType::Cow,
            'quantity_litre' => '10.00',
            'rate_per_litre' => '40.00',
            'amount' => '400.00',
            'quality_status' => QualityStatus::Accepted,
        ];
    }
}
