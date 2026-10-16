<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'parent_id' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'code' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** A subcategory of $parent (same farm). */
    public function childOf(ExpenseCategory $parent): static
    {
        return $this->state(fn () => ['farm_id' => $parent->farm_id, 'parent_id' => $parent->id]);
    }
}
