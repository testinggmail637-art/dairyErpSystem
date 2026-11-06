<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanType;
use App\Enums\RepaymentMethod;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit a loan (spec Phase 3A). Tenure is entered as years + months. Once the loan has transactions its terms
 * are locked (only lender details, notes, OD renewal date). Edits are audit-logged with a reason.
 */
class LoanRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('loans.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => str_replace([',', ' ', '₹', '%'], '', trim((string) $this->input($key)));
        $years = (int) $this->input('tenure_years');
        $months = (int) $this->input('tenure_extra_months');

        $this->merge([
            'principal_amount' => $clean('principal_amount'),
            'interest_rate' => $clean('interest_rate'),
            'processing_fee' => $clean('processing_fee') === '' ? '0' : $clean('processing_fee'),
            'tenure_months' => $years * 12 + $months ?: null,
            'lender_name' => trim((string) $this->input('lender_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $loan = $this->route('loan');
        $editing = $loan instanceof Loan;
        $locked = $editing && app(LoanService::class)->hasTransactions($loan);
        $od = $locked ? $loan->isOverdraft() : $this->input('loan_type') === LoanType::Overdraft->value;
        $termRule = fn (array $rules) => $locked ? ['exclude'] : $rules;
        $scheduleRule = fn (array $rules) => $locked || $od ? ['exclude'] : $rules;

        return ($editing ? $this->reasonRules() : []) + [
            'loan_type' => $termRule(['required', Rule::enum(LoanType::class)]),
            'lender_name' => ['required', 'string', 'max:150'],
            'lender_phone' => ['nullable', 'string', 'max:15'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'principal_amount' => $termRule(['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99']),
            'interest_rate' => $termRule(['required', 'decimal:0,2', 'between:0,99.99']),
            'repayment_method' => $scheduleRule(['required', Rule::enum(RepaymentMethod::class)]),
            'tenure_months' => $scheduleRule(['required', 'integer', 'between:1,480']),
            'start_date' => $termRule(['required', 'date_format:Y-m-d', 'before_or_equal:today']),
            'first_due_date' => $scheduleRule(['required', 'date_format:Y-m-d', 'after:start_date']),
            'processing_fee' => $termRule(['required', 'decimal:0,2', 'min:0', 'max:9999999999.99']),
            'od_renewal_date' => $od ? ['nullable', 'date_format:Y-m-d'] : ['exclude'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenure_months.required' => __('Enter the loan period in years and / or months.'),
            'tenure_months.between' => __('The loan period must be 1 month to 40 years.'),
            'first_due_date.after' => __('The first EMI date must be after the loan start date.'),
            'start_date.before_or_equal' => __('The start date cannot be in the future.'),
            'interest_rate.between' => __('Enter the yearly interest rate in % (0 to 99.99).'),
        ];
    }
}
