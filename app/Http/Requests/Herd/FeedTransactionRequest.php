<?php

namespace App\Http\Requests\Herd;

use App\Enums\FeedTransactionType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\ExpenseCategory;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A feed stock entry. Purchase: bill amount (rate follows), paid by a mode (not "Card" — card purchases go through
 * Expenses) or credit with a vendor, optional Feed subcategory. Consumption: optionally one animal. Changing one
 * needs a reason.
 */
class FeedTransactionRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('feed.manage');
    }

    protected function prepareForValidation(): void
    {
        $num = fn (string $key) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $this->input($key)))) === '' ? null : $v;

        $this->merge([
            'quantity' => $num('quantity'),
            'rate' => $num('rate'),
            'amount' => $num('amount'),
            'is_credit' => $this->boolean('is_credit'),
            'vendor_id' => $this->input('vendor_id') ?: null,
            'payment_mode_id' => $this->boolean('is_credit') ? null : ($this->input('payment_mode_id') ?: null),
            'animal_id' => $this->input('animal_id') ?: null,
            'expense_subcategory_id' => $this->input('expense_subcategory_id') ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $purchase = $this->input('type') === FeedTransactionType::Purchase->value;
        $feedId = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Feed')->value('id');

        return [
            'feed_item_id' => ['required', 'integer', Rule::exists('feed_items', 'id')->where('farm_id', $farmId)
                ->when(! $this->isMethod('PUT'), fn ($r) => $r->where('is_active', true))],
            'type' => ['required', Rule::enum(FeedTransactionType::class)],
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'amount' => [Rule::requiredIf($purchase), 'nullable', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'rate' => ['nullable', 'decimal:0,2', 'gte:0', 'max:99999999.99'],
            'is_credit' => ['boolean'],
            'vendor_id' => [Rule::requiredIf($purchase && $this->boolean('is_credit')), 'nullable', 'integer',
                Rule::exists('vendors', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'payment_mode_id' => [Rule::requiredIf($purchase && ! $this->boolean('is_credit')), 'nullable', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)->whereNot('code', 'CARD')],
            'expense_subcategory_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('parent_id', $feedId)],
            'animal_id' => ['nullable', 'integer', Rule::exists('animals', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
        ] + ($this->isMethod('PUT') ? $this->reasonRules() : []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_date.before_or_equal' => __('The date cannot be in the future.'),
            'quantity.gt' => __('The quantity must be more than zero.'),
            'amount.required' => __('Enter the bill amount of the purchase.'),
            'vendor_id.required' => __('A credit (unpaid) purchase needs a vendor.'),
            'payment_mode_id.required' => __('Choose how it was paid, or mark it as credit.'),
            'payment_mode_id.exists' => __('Choose a payment mode. Card purchases are entered under Expenses.'),
        ];
    }
}
