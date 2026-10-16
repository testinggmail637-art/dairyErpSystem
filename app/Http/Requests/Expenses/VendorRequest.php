<?php

namespace App\Http\Requests\Expenses;

use App\Models\User;
use App\Models\Vendor;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('vendors.manage');
    }

    protected function prepareForValidation(): void
    {
        $gstin = strtoupper(trim((string) $this->input('gstin')));
        $opening = str_replace([',', ' ', '₹'], '', trim((string) $this->input('opening_balance')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => User::normalizePhone($this->input('phone')),
            'gstin' => $gstin === '' ? null : $gstin,
            'opening_balance' => $opening === '' ? '0' : $opening,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'name' => [
                'required', 'string', 'max:150',
                Rule::unique('vendors', 'name')
                    ->where('farm_id', app(CurrentFarm::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($vendor instanceof Vendor ? $vendor->id : null),
            ],
            'phone' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'opening_balance' => ['required', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => __('A vendor with this name already exists.'),
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'gstin.regex' => __('Enter a valid 15-character GSTIN, e.g. 27ABCDE1234F1Z5.'),
            'opening_balance.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
        ];
    }
}
