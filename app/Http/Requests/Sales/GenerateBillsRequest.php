<?php

namespace App\Http\Requests\Sales;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Generate draft bills for one customer or for every customer with unbilled milk in the period. */
class GenerateBillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bills.generate');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['one', 'all'])],
            'customer_id' => ['exclude_unless:scope,one', 'required', 'integer',
                Rule::exists('customers', 'id')->where('farm_id', app(CurrentFarm::class)->id())],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from', 'before_or_equal:today'],
            'bill_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_to'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_to.before_or_equal' => __('A bill cannot cover days that have not happened yet.'),
            'bill_date.after_or_equal' => __('The bill date must be on or after the last day of the period.'),
        ];
    }
}
