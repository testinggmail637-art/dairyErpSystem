<?php

namespace App\Http\Requests\Expenses;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\VendorPayment;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record / edit a payment to a vendor. Editing needs a reason (vendor payments are audit-logged, spec 4.4). */
class VendorPaymentRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $payment = $this->payment();

        return $payment ? $this->user()->can('update', $payment) : $this->user()->can('create', VendorPayment::class);
    }

    protected function prepareForValidation(): void
    {
        $amount = str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount')));

        $this->merge([
            'amount' => $amount === '' ? null : $amount,
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $currentMode = $this->payment()?->payment_mode_id;

        return ($this->payment() ? $this->reasonRules() : []) + [
            // any vendor of this farm that is not deleted (paying old dues to an inactive vendor is fine)
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)
                ->where(fn ($q) => $q->where('is_active', true)->when($currentMode, fn ($q) => $q->orWhere('id', $currentMode)))],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_date.before_or_equal' => __('The payment date cannot be in the future.'),
            'amount.gt' => __('The amount must be more than zero.'),
            'amount.decimal' => __('Enter the amount in rupees with up to 2 decimals.'),
        ];
    }

    public function payment(): ?VendorPayment
    {
        $payment = $this->route('vendor_payment');

        return $payment instanceof VendorPayment ? $payment : null;
    }
}
