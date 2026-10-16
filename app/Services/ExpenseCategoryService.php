<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use DomainException;

/**
 * Expense categories / subcategories (spec Section 6, Phase 1). Two levels only.
 */
class ExpenseCategoryService
{
    /** @param  array{name: string, code?: ?string, parent_id?: ?int, sort_order?: ?int, is_active: bool}  $data */
    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($this->attributes($data));
    }

    /** @param  array{name: string, code?: ?string, parent_id?: ?int, sort_order?: ?int, is_active: bool}  $data */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->update($this->attributes($data));

        return $category;
    }

    /**
     * Only unused rows can be deleted; otherwise deactivate them so old records keep their category.
     *
     * @throws DomainException
     */
    public function delete(ExpenseCategory $category): void
    {
        if ($category->children()->exists()) {
            throw new DomainException(__('Delete or move its subcategories first, or deactivate the category instead.'));
        }

        // Expenses (Task 1.3) will also block deletion here.

        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) $data['is_active'],
        ];
    }
}
