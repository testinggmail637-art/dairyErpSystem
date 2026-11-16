<?php

namespace App\Http\Requests\Staff;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Record that a finalized slip's salary was paid. */
class PaySalarySlipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('salary.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('payment_modes', 'id')
                ->where('farm_id', app(CurrentFarm::class)->id())->where('is_active', true)],
        ];
    }
}
