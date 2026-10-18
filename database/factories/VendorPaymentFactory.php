<?php

namespace Database\Factories;

use App\Models\PaymentMode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Use ->for($vendor) or pass vendor_id; farm_id and payment mode follow the vendor's farm.
 *
 * @extends Factory<VendorPayment>
 */
class VendorPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory(),
            'farm_id' => fn (array $a) => Vendor::withoutGlobalScopes()->find($a['vendor_id'])->farm_id,
            'payment_date' => now()->toDateString(),
            'amount' => (string) fake()->numberBetween(500, 20000).'.00',
            'payment_mode_id' => fn (array $a) => PaymentMode::factory()->create(['farm_id' => $a['farm_id']])->id,
            'reference_no' => null,
            'note' => null,
        ];
    }
}
