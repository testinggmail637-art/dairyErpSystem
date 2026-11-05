<?php

namespace Database\Factories;

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'farm_id' => fn (array $a) => Customer::allFarms()->withTrashed()->findOrFail($a['customer_id'])->farm_id,
            'product_id' => fn (array $a) => Product::factory()->create(['farm_id' => $a['farm_id']])->id,
            'shift' => MilkShift::Morning,
            'quantity' => '2.00',
            'stock_source' => MilkSource::OwnFarm,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'status' => SubscriptionStatus::Active,
        ];
    }
}
