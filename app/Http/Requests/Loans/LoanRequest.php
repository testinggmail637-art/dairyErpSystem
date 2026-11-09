<?php

namespace App\Http\Requests\Loans;

use App\Enums\InstallmentFrequency;
use App\Enums\LoanFamily;
use App\Enums\LoanType;
use App\Enums\MoratoriumInterest;
use App\Enums\RepaymentMethod;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Loan;
use App\Services\LoanService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add / edit a loan, revolving account or credit card (spec §6 Phase 3A). Rules follow the loan family. Period is
 * entered as years + months; the rate as % per year or per month (stored per year). Once the loan has transactions its
 * terms are locked (only lender details, notes, renewal date). Edits are audit-logged with a reason.
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
        $rate = $clean('interest_rate');
        $months = (int) $this->input('tenure_years') * 12 + (int) $this->input('tenure_extra_months');

        $this->merge([
            'principal_amount' => $clean('principal_amount'),
            // "% per month" (private lenders, cards) is stored per year
            'interest_rate' => $rate !== '' && is_numeric($rate) && $this->input('rate_basis') === 'month' ? Money::mul($rate, '12') : $rate,
            'processing_fee' => $clean('processing_fee') === '' ? '0' : $clean('processing_fee'),
            'broken_period_interest' => $clean('broken_period_interest') === '' ? null : $clean('broken_period_interest'),
            'tenure_months' => $months ?: null,
            'moratorium_months' => (int) $this->input('moratorium_months'),
            'installment_frequency' => $this->input('installment_frequency') ?: 'monthly',
            'interest_debit_cycle' => $this->input('interest_debit_cycle') ?: 'monthly',
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
        $type = $locked ? $loan->loan_type : LoanType::tryFrom((string) $this->input('loan_type'));
        $family = $type?->family();
        $method = RepaymentMethod::tryFrom((string) $this->input('repayment_method'));
        $frequency = InstallmentFrequency::tryFrom((string) $this->input('installment_frequency')) ?? InstallmentFrequency::Monthly;

        $when = fn (bool $applies, array $rules) => ! $locked && $applies ? $rules : ['exclude'];
        $instalment = $family === LoanFamily::Instalment;
        $scheduled = $instalment && $method !== RepaymentMethod::Bullet;
        $multiple = fn (string $label) => function ($attr, $value, $fail) use ($frequency, $label) {
            if ((int) $value % $frequency->months() !== 0) {
                $fail(__('The :what must be a whole number of :period periods.', ['what' => $label, 'period' => mb_strtolower($frequency->label())]));
            }
        };

        return ($editing ? $this->reasonRules() : []) + [
            'loan_type' => $when(true, ['required', Rule::enum(LoanType::class)]),
            'lender_name' => ['required', 'string', 'max:150'],
            'lender_phone' => ['nullable', 'string', 'max:15'],
            'account_no' => ['nullable', 'string', 'max:50'],
            'principal_amount' => $when(true, ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99']),
            'interest_rate' => $when(true, ['required', 'decimal:0,2', 'between:0,99.99']),
            'processing_fee' => $when(true, ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99']),
            'start_date' => $when(true, ['required', 'date_format:Y-m-d', 'before_or_equal:today']),

            'repayment_method' => $when($instalment, ['required', Rule::enum(RepaymentMethod::class)]),
            'tenure_months' => $when($instalment, ['required', 'integer', 'between:1,480', ...($scheduled ? [$multiple(__('loan period'))] : [])]),
            'installment_frequency' => $when($scheduled, ['required', Rule::enum(InstallmentFrequency::class)]),
            'moratorium_months' => $when($scheduled, ['integer', 'min:0', 'lt:tenure_months', $multiple(__('moratorium'))]),
            'moratorium_interest' => $when($scheduled && (int) $this->input('moratorium_months') > 0, ['required', Rule::enum(MoratoriumInterest::class)]),
            'first_due_date' => $when($scheduled, ['required', 'date_format:Y-m-d', 'after:start_date']),
            'broken_period_interest' => $when($scheduled, ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99']),

            'interest_debit_cycle' => $when($family === LoanFamily::Revolving, ['required', Rule::enum(InstallmentFrequency::class)]),
            'statement_day' => $when($family === LoanFamily::CreditCard, ['required', 'integer', 'between:1,31']),
            'due_day' => $when($family === LoanFamily::CreditCard, ['required', 'integer', 'between:1,31']),
            'od_renewal_date' => $family === LoanFamily::Revolving ? ['nullable', 'date_format:Y-m-d'] : ['exclude'],
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
            'moratorium_months.lt' => __('The moratorium must be shorter than the loan period.'),
            'first_due_date.after' => __('The first instalment date must be after the loan start date.'),
            'start_date.before_or_equal' => __('The start date cannot be in the future.'),
            'interest_rate.between' => __('Enter the interest rate: 0 to 99.99 % a year (0 to 8.33 % a month).'),
        ];
    }
}
