<?php

namespace App\Http\Requests\Loans;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An instalment payment (split optional — empty = calculated) or a bank charge on an instalment loan (spec 7.4A).
 * Editing the latest entry needs a reason.
 */
class LoanPaymentRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $txn = $this->txn();

        return $txn ? $this->user()->can('update', $txn) : $this->user()->can('loan_payments.create');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $this->input($key)))) === '' ? null : $v;

        $this->merge([
            'kind' => $this->input('kind') === 'charge' ? 'charge' : 'payment',
            'amount' => $clean('amount'),
            'interest_part' => $clean('interest_part'),
            'principal_part' => $clean('principal_part'),
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
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
            'kind' => ['required', Rule::in(['payment', 'charge'])],
            'txn_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$loan->start_date->toDateString()],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'interest_part' => ['exclude_if:kind,charge', 'nullable', 'decimal:0,2', 'min:0'],
            'principal_part' => ['exclude_if:kind,charge', 'nullable', 'decimal:0,2', 'min:0'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', app(CurrentFarm::class)->id())
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
        return ['txn_date.after_or_equal' => __('The date cannot be before the loan start date.')];
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
