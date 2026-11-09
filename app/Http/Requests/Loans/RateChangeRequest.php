<?php

namespace App\Http\Requests\Loans;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Loan;
use App\Services\LoanRescheduleService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The bank changed the interest rate (floating rate): new EMI, or same EMI with a different period (RBI 2023). */
class RateChangeRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('loans.manage');
    }

    protected function prepareForValidation(): void
    {
        $rate = str_replace([',', ' ', '%'], '', trim((string) $this->input('new_rate')));

        $this->merge(['new_rate' => $rate !== '' && is_numeric($rate) && $this->input('rate_basis') === 'month' ? Money::mul($rate, '12') : $rate]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');

        return $this->reasonRules() + [
            'effective_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$loan->start_date->toDateString()],
            'new_rate' => ['required', 'decimal:0,2', 'between:0,99.99', 'not_in:'.$loan->interest_rate],
            'mode' => ['required', Rule::in([LoanRescheduleService::REDUCE_EMI, LoanRescheduleService::REDUCE_TENURE])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['new_rate.not_in' => __('This is already the loan\'s rate.')];
    }
}
