<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Paid (cash) expense by default; ->credit($vendor) for an unpaid one. Pass farm_id to keep related rows in one farm.
 *
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'expense_date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'amount' => (string) fake()->numberBetween(100, 50000).'.00',
            'category_id' => fn (array $a) => ExpenseCategory::factory()->create(['farm_id' => $a['farm_id']])->id,
            'subcategory_id' => null,
            'section_id' => fn (array $a) => Section::factory()->create(['farm_id' => $a['farm_id']])->id,
            'vendor_id' => null,
            'paid_to_text' => fake()->name(),
            'is_credit' => false,
            'payment_mode_id' => fn (array $a) => PaymentMode::factory()->create(['farm_id' => $a['farm_id']])->id,
            'note' => null,
        ];
    }

    public function credit(Vendor $vendor): static
    {
        return $this->state(fn () => [
            'farm_id' => $vendor->farm_id,
            'is_credit' => true,
            'vendor_id' => $vendor->id,
            'paid_to_text' => null,
            'payment_mode_id' => null,
        ]);
    }
}
