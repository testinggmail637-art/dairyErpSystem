<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

/** Filters of the expense list, totals and Excel export. */
class ExpenseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Export: full viewers only. List: also create-only staff (the controller limits them to their own entries).
        return $this->routeIs('expenses.export')
            ? $this->user()->can('expenses.view')
            : $this->user()->can('viewAny', Expense::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category_id' => ['nullable', 'integer'],
            'subcategory_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'animal_id' => ['nullable', 'integer'],
            'payment_mode_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:paid,credit'],
            'created_by' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'import_batch_id' => ['nullable', 'integer'], // "view the expenses" of an import (spec 7.2 step 5)
            'all' => ['nullable', 'in:1'],
        ];
    }

    /**
     * The first visit (no query string) shows the current month; "Clear" / emptied dates show everything.
     * Keeps the default page fast on years of data.
     */
    protected function prepareForValidation(): void
    {
        $firstVisit = $this->query->count() === 0 && ! $this->hasHeader('X-Ajax-List');

        if ($firstVisit && ! $this->routeIs('expenses.export')) {
            $this->merge(['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]);
        }

        if ($this->input('all') === '1') {
            $this->merge(['from' => null, 'to' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_merge(array_fill_keys(array_keys($this->rules()), null), $this->validated());
    }
}
