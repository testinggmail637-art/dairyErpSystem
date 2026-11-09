<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanTxnType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Services\RevolvingAccountService;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An entry on an overdraft / cash credit / KCC account (spec 7.4A). Editing the latest entry needs a reason. */
class RevolvingEntryRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $txn = $this->txn();

        return $txn ? $this->user()->can('update', $txn) : $this->user()->can('loan_payments.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
            'payment_mode_id' => $this->input('payment_mode_id') ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $loan = $this->loan();
        $mode = $this->txn()?->payment_mode_id;

        return ($this->txn() ? $this->reasonRules() : []) + [
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, RevolvingAccountService::typesFor($loan)))],
            'txn_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$loan->start_date->toDateString()],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required_if:type,'.LoanTxnType::OdRepayment->value, 'nullable', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', app(CurrentFarm::class)->id())
                    ->where(fn ($q) => $q->where('is_active', true)->when($mode, fn ($q) => $q->orWhere('id', $mode)))],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_mode_id.required_if' => __('Choose how the repayment was made.'),
            'txn_date.after_or_equal' => __('The date cannot be before the account start date.'),
        ];
    }

    public function loan(): Loan
    {
        return $this->txn()?->loan ?? $this->route('loan');
    }

    private function txn(): ?LoanTransaction
    {
        $txn = $this->route('loan_transaction');

        return $txn instanceof LoanTransaction ? $txn : null;
    }
}
