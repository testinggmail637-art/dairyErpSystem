<?php

namespace App\Http\Requests\Trading;

use App\Enums\PartyType;
use App\Models\Party;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animal_trading.manage');
    }

    protected function prepareForValidation(): void
    {
        $opening = str_replace([',', ' ', '₹'], '', trim((string) $this->input('opening_balance')));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => User::normalizePhone($this->input('phone')),
            'village' => trim((string) $this->input('village')) ?: null,
            'opening_balance' => $opening === '' ? '0' : $opening,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $party = $this->route('party');

        return [
            'name' => ['required', 'string', 'max:150',
                // same name allowed in different villages (common names); not twice in one village
                Rule::unique('parties', 'name')->where('farm_id', app(CurrentFarm::class)->id())->whereNull('deleted_at')
                    ->where(fn ($q) => $this->input('village') === null ? $q->whereNull('village') : $q->where('village', $this->input('village')))
                    ->ignore($party instanceof Party ? $party->id : null)],
            'phone' => ['nullable', 'digits:10'],
            'village' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'party_type' => ['required', Rule::enum(PartyType::class)],
            'opening_balance' => ['required', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => __('A party with this name already exists in this village.'),
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'opening_balance.decimal' => __('Enter an amount in rupees with up to 2 decimals.'),
        ];
    }
}
