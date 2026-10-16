<?php

namespace App\Http\Requests\Expenses;

use App\Enums\ExpenseUnit;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Expense;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Create / edit an expense (spec 6 + 7.1). Every lookup must belong to the current farm; inactive categories,
 * sections, vendors and payment modes are refused for new values but an existing expense may keep its own.
 */
class ExpenseRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        // ExpensePolicy: edit permission, or a create-only user's own entry on the same day (spec 4.5).
        $expense = $this->expense();

        return $expense ? $this->user()->can('update', $expense) : $this->user()->can('create', Expense::class);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? null : $v;

        $this->merge([
            'amount' => $clean($this->input('amount')),
            'quantity' => $clean($this->input('quantity')),
            'is_credit' => $this->boolean('is_credit'),
            'paid_to_text' => trim((string) $this->input('paid_to_text')) ?: null,
            'subcategory_id' => $this->input('subcategory_id') ?: null,
            'vendor_id' => $this->input('vendor_id') ?: null,
            'payment_mode_id' => $this->boolean('is_credit') ? null : ($this->input('payment_mode_id') ?: null),
            'unit' => $this->input('unit') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Editing an existing expense is audit-logged and needs a reason (spec 4.4).
        $reason = $this->expense() ? $this->reasonRules() : [];

        return $reason + [
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            // must be a top-level category …
            'category_id' => ['required', 'integer', $this->lookup('expense_categories', 'category_id')->whereNull('parent_id')],
            // … and the subcategory must be a child of that category (server-side, spec Phase 1 acceptance)
            'subcategory_id' => ['nullable', 'integer',
                $this->lookup('expense_categories', 'subcategory_id')->where('parent_id', (int) $this->input('category_id'))],
            'section_id' => ['required', 'integer', $this->lookup('sections', 'section_id')],
            'is_credit' => ['boolean'],
            'vendor_id' => ['nullable', 'required_if:is_credit,true', 'integer',
                $this->lookup('vendors', 'vendor_id')->whereNull('deleted_at')],
            'paid_to_text' => ['nullable', 'string', 'max:150'],
            'payment_mode_id' => ['nullable', 'required_if:is_credit,false', 'integer', $this->lookup('payment_modes', 'payment_mode_id')],
            'quantity' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'unit' => ['nullable', 'required_with:quantity', Rule::enum(ExpenseUnit::class)],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'remove_receipt' => ['boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_date.before_or_equal' => __('The expense date cannot be in the future.'),
            'amount.gt' => __('The amount must be more than zero.'),
            'amount.decimal' => __('Enter the amount in rupees with up to 2 decimals.'),
            'category_id.exists' => __('Choose a main category.'),
            'subcategory_id.exists' => __('This subcategory does not belong to the chosen category.'),
            'vendor_id.required_if' => __('A credit (unpaid) expense needs a vendor.'),
            'payment_mode_id.required_if' => __('Choose how it was paid, or mark the expense as credit.'),
            'unit.required_with' => __('Choose the unit for the quantity.'),
            'receipt.mimes' => __('The receipt must be a JPG, PNG or PDF file.'),
            'receipt.max' => __('The receipt may not be larger than 5 MB.'),
        ];
    }

    public function expense(): ?Expense
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense ? $expense : null;
    }

    /** exists:{table},id in this farm, active — or the value this expense already has. */
    private function lookup(string $table, string $field): Exists
    {
        $current = $this->expense()?->getAttribute($field);

        return Rule::exists($table, 'id')
            ->where('farm_id', app(CurrentFarm::class)->id())
            ->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)));
    }
}
