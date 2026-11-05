<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'farm_id' => fn (array $a) => Customer::allFarms()->withTrashed()->findOrFail($a['customer_id'])->farm_id,
            'product_id' => fn (array $a) => Product::factory()->create(['farm_id' => $a['farm_id']])->id,
            'delivery_date' => now()->toDateString(),
            'shift' => MilkShift::Morning,
            'stock_source' => MilkSource::OwnFarm,
            'planned_qty' => '2.00',
            'delivered_qty' => '0.00',
            'rate' => '60.00',
            'amount' => '0.00',
            'status' => DeliveryStatus::Pending,
        ];
    }
}
