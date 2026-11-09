<?php

namespace App\Http\Requests\Collection;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Mark a farmer statement paid (date, mode, reference) — or, without a mode, all of a cycle as cash. */
class MarkFarmerPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('farmer_payments.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reference_no' => trim((string) $this->input('reference_no')) ?: null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $all = $this->routeIs('farmer-payments.pay-all');

        return [
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_mode_id' => [$all ? 'nullable' : 'required', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', app(CurrentFarm::class)->id())->where('is_active', true)],
            'reference_no' => ['nullable', 'string', 'max:50'],
        ];
    }
}
