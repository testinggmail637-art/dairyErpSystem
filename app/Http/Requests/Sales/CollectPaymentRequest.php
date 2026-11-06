<?php

namespace App\Http\Requests\Sales;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Collect payment" on a delivery sheet row: cash / UPI received from that row's customer today. */
class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('collectPayment', $this->route('delivery'));
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
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', app(CurrentFarm::class)->id())->where('is_active', true)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
