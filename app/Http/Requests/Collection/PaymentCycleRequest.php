<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

/** Make a farmer payment cycle for a date range (spec Phase 4). */
class PaymentCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('farmer_payments.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['to_date.before_or_equal' => __('A cycle cannot end in the future.')];
    }
}
