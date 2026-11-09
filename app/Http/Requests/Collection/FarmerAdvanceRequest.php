<?php

namespace App\Http\Requests\Collection;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Give a farmer an advance (spec Phase 4): date, amount, how it was paid. */
class FarmerAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('farmer_advances.manage');
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

        return [
            'farmer_id' => ['required', 'integer', Rule::exists('farmers', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'advance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
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
            'advance_date.before_or_equal' => __('The date cannot be in the future.'),
            'amount.gt' => __('The amount must be more than zero.'),
            'amount.decimal' => __('Enter the amount in rupees with up to 2 decimals.'),
        ];
    }
}
