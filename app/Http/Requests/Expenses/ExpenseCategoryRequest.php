<?php

namespace App\Http\Requests\Expenses;

use App\Models\ExpenseCategory;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit an expense category or subcategory. Two levels only; names unique within the same parent.
 */
class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.edit');
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $code === '' ? null : $code,
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $category = $this->category();
        $parentId = $this->input('parent_id');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where('farm_id', $farmId)
                    ->where(fn ($q) => $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId))
                    ->ignore($category?->id),
            ],
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'],
            // A subcategory's parent must be a top-level category of this farm (no third level).
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('expense_categories', 'id')->where('farm_id', $farmId)->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id])),
            ],
            'sort_order' => ['integer', 'between:0,9999'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $category = $this->category();

            if ($category && $this->input('parent_id') !== null && $category->children()->exists()) {
                $validator->errors()->add('parent_id', __('This category has subcategories, so it cannot become a subcategory itself.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('This name already exists here.'),
            'parent_id.exists' => __('Choose a main category (subcategories cannot have their own subcategories).'),
            'parent_id.not_in' => __('A category cannot be its own parent.'),
            'code.regex' => __('Use capital letters, numbers, - and _ only.'),
        ];
    }

    private function category(): ?ExpenseCategory
    {
        $category = $this->route('expense_category');

        return $category instanceof ExpenseCategory ? $category : null;
    }
}
