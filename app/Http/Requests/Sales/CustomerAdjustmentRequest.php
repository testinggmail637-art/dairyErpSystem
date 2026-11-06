<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerAdjustment;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/** Signed amount: + customer owes more, − discount / correction. Reason is part of the record (spec Section 6). */
class CustomerAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerAdjustment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'reason' => trim((string) $this->input('reason')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'adjustment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'between:-9999999999.99,9999999999.99',
                fn ($attr, $value, $fail) => Money::isZero($value) && $fail(__('Enter an amount other than 0 (minus for a discount).'))],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
        ];
    }
}
