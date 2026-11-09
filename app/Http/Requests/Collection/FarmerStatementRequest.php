<?php

namespace App\Http\Requests\Collection;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bonus and deductions of a draft statement (spec Phase 4). */
class FarmerStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('farmer_payments.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $this->input($key)))) === '' ? '0' : $v;

        $this->merge([
            'bonus' => $clean('bonus'),
            'advance_deduction' => $clean('advance_deduction'),
            'other_deduction' => $clean('other_deduction'),
            'deduction_note' => trim((string) $this->input('deduction_note')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

        return [
            'bonus' => $money,
            'advance_deduction' => $money,
            'other_deduction' => $money,
            'deduction_note' => ['nullable', Rule::requiredIf(fn () => is_numeric($this->input('other_deduction')) && Money::compare($this->input('other_deduction'), '0') > 0),
                'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['deduction_note.required' => __('Say what the other deduction is for.')];
    }
}
