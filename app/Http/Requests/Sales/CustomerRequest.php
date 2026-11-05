<?php

namespace App\Http\Requests\Sales;

use App\Enums\B2bCategory;
use App\Enums\CustomerType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Customer;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a customer. Customers are audit-logged, so an edit needs a reason (spec 4.4). */
class CustomerRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('customers.manage');
    }

    protected function prepareForValidation(): void
    {
        $gstin = strtoupper(trim((string) $this->input('gstin')));
        $opening = str_replace([',', ' ', '₹'], '', trim((string) $this->input('opening_balance')));
        $limit = str_replace([',', ' ', '₹'], '', trim((string) $this->input('credit_limit')));

        $this->merge([
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('name')) ?? ''),
            'phone' => User::normalizePhone($this->input('phone')),
            'alt_phone' => User::normalizePhone($this->input('alt_phone')),
            'gstin' => $gstin === '' ? null : $gstin,
            'opening_balance' => $opening === '' ? '0' : $opening,
            'credit_limit' => $limit === '' ? null : $limit,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();

        return ($this->route('customer') instanceof Customer ? $this->reasonRules() : []) + [
            'name' => ['required', 'string', 'max:150'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'b2b_category' => ['nullable', 'required_if:customer_type,'.CustomerType::B2b->value, Rule::enum(B2bCategory::class)],
            'phone' => ['nullable', 'digits:10'],
            'alt_phone' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:100'],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')->where('farm_id', $farmId)],
            'route_sequence' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'opening_balance' => ['required', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
            'credit_limit' => ['nullable', 'decimal:0,2', 'between:0,9999999999.99'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'b2b_category.required_if' => __('Choose the kind of business for a B2B customer.'),
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'alt_phone.digits' => __('Enter a 10-digit mobile number.'),
            'gstin.regex' => __('Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5.'),
            'opening_balance.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
            'credit_limit.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
        ];
    }
}
