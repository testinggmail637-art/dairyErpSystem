<?php

namespace App\Http\Requests\Sales;

use App\Models\Customer;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A customer's own rate for a product from a date (spec Section 6). One rate per product and date. */
class CustomerRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['rate' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('rate')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'product_id' => ['required', 'integer',
                Rule::exists('products', 'id')->where('farm_id', app(CurrentFarm::class)->id())->whereNull('deleted_at')],
            'rate' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'effective_from' => ['required', 'date_format:Y-m-d',
                Rule::unique('customer_rates')->where('customer_id', $customer->id)->where('product_id', $this->input('product_id'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'effective_from.unique' => __('This customer already has a rate for this product from this date. Remove it first or pick another date.'),
            'rate.decimal' => __('Enter a rate in rupees with up to 2 decimals.'),
        ];
    }
}
