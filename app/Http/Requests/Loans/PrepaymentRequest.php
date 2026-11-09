<?php

namespace App\Http\Requests\Loans;

use App\Models\Loan;
use App\Services\LoanRescheduleService;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Pay principal early: lower EMI (same period) or same EMI (shorter period) — spec 7.4A. */
class PrepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('loan_payments.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');

        return [
            'txn_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$loan->start_date->toDateString()],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'mode' => ['required', Rule::in([LoanRescheduleService::REDUCE_EMI, LoanRescheduleService::REDUCE_TENURE])],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', app(CurrentFarm::class)->id())->where('is_active', true)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid', Rule::unique('loan_transactions', 'client_uuid')],
        ];
    }
}
