<?php

namespace App\Http\Requests\Trading;

use App\Enums\AnimalSaleStatus;
use App\Enums\PaymentDirection;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Record a payment to / from a party (spec Phase 6). It may name the purchase (money paid) or the sale (money
 * received) it is for — that one must belong to the same party; a cancelled sale cannot take payments.
 */
class AnimalPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animal_trading.manage');
    }

    protected function prepareForValidation(): void
    {
        $paid = $this->input('direction') === PaymentDirection::Paid->value;

        $this->merge([
            'amount' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('amount'))),
            'reference_no' => trim((string) $this->input('reference_no')) ?: null,
            'note' => trim((string) $this->input('note')) ?: null,
            // only the document that fits the direction is kept
            'animal_purchase_id' => $paid ? ($this->input('animal_purchase_id') ?: null) : null,
            'animal_sale_id' => $paid ? null : ($this->input('animal_sale_id') ?: null),
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $partyId = (int) $this->input('party_id');

        return [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'direction' => ['required', Rule::enum(PaymentDirection::class)],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'animal_purchase_id' => ['nullable', 'integer', Rule::exists('animal_purchases', 'id')->where('farm_id', $farmId)->where('party_id', $partyId)],
            'animal_sale_id' => ['nullable', 'integer', Rule::exists('animal_sales', 'id')->where('farm_id', $farmId)->where('party_id', $partyId)
                ->where('status', AnimalSaleStatus::Completed->value)],
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
            'animal_purchase_id.exists' => __('Choose a purchase from this party.'),
            'animal_sale_id.exists' => __('Choose a sale to this party that is not cancelled.'),
        ];
    }
}
