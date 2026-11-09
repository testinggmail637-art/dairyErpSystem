<?php

namespace App\Http\Requests\Collection;

use App\Enums\MilkType;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Farmer;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a farmer. Farmers are audit-logged, so an edit needs a reason (spec 4.4). */
class FarmerRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('farmers.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $this->input($key)) ?? '')) === '' ? null : $v;
        $opening = str_replace([',', ' ', '₹'], '', trim((string) $this->input('opening_balance')));

        $this->merge([
            'name' => (string) $clean('name'),
            'father_name' => $clean('father_name'),
            'village' => $clean('village'),
            'phone' => User::normalizePhone($this->input('phone')),
            'bank_account_name' => $clean('bank_account_name'),
            'bank_account_no' => str_replace(' ', '', (string) $this->input('bank_account_no')) ?: null,
            'ifsc' => strtoupper(str_replace(' ', '', (string) $this->input('ifsc'))) ?: null,
            'upi_id' => strtolower(trim((string) $this->input('upi_id'))) ?: null,
            'opening_balance' => $opening === '' ? '0' : $opening,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
            'rate_chart_id' => $this->input('rate_chart_id') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ($this->route('farmer') instanceof Farmer ? $this->reasonRules() : []) + [
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'digits:10'],
            'village' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'default_milk_type' => ['required', Rule::enum(MilkType::class)],
            // own rate chart (overrides the default chart), same milk type, of this farm
            'rate_chart_id' => ['nullable', 'integer', Rule::exists('rate_charts', 'id')->where('farm_id', app(CurrentFarm::class)->id())
                ->where('milk_type', (string) $this->input('default_milk_type'))->whereNull('deleted_at')],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'regex:/^\d{9,18}$/'],
            'ifsc' => ['nullable', 'required_with:bank_account_no', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'upi_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9._-]{2,}@[a-z][a-z0-9.]{1,}$/'],
            'opening_balance' => ['required', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
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
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'rate_chart_id.exists' => __('Choose a rate chart for the same milk as the farmer\'s usual milk.'),
            'bank_account_no.regex' => __('Enter the account number: 9 to 18 digits.'),
            'ifsc.required_with' => __('Enter the IFSC code of the bank branch.'),
            'ifsc.regex' => __('Enter a valid 11-character IFSC, e.g. SBIN0001234.'),
            'upi_id.regex' => __('Enter a valid UPI ID, e.g. ramesh@upi.'),
            'opening_balance.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
        ];
    }
}
