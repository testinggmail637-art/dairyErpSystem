<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Bonus and deductions of a draft slip; slips are audit-logged, so a change needs a reason (spec 4.4). */
class SalarySlipRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('salary.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? '0' : $v;

        $this->merge([
            'bonus' => $clean($this->input('bonus')),
            'advance_deduction' => $clean($this->input('advance_deduction')),
            'other_deduction' => $clean($this->input('other_deduction')),
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $money = ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'];

        return $this->reasonRules() + [
            'bonus' => $money,
            'advance_deduction' => $money,
            'other_deduction' => $money,
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
