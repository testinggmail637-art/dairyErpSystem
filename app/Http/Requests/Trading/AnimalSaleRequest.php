<?php

namespace App\Http\Requests\Trading;

use App\Enums\AnimalStatus;
use App\Enums\PartyType;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** New animal sale: buyer, date, active animals with their price, selling costs (spec Phase 6). */
class AnimalSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('animal_trading.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' ', '₹'], '', trim((string) $v))) === '' ? null : $v;

        $rows = collect((array) $this->input('animals'))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->reject(fn ($row) => trim((string) ($row['animal_id'] ?? '')) === '' && trim((string) ($row['price'] ?? '')) === '')
            ->map(fn ($row) => ['animal_id' => $row['animal_id'] ?? null, 'price' => $clean($row['price'] ?? null)])
            ->values()
            ->all();

        $this->merge([
            'selling_costs' => $clean($this->input('selling_costs')) ?? '0',
            'animals' => $rows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();

        return [
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')
                ->where('farm_id', $farmId)->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('party_type', [PartyType::Buyer->value, PartyType::Both->value])],
            'sale_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'selling_costs' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'animals' => ['required', 'array', 'min:1', 'max:50'],
            'animals.*.animal_id' => ['required', 'integer', 'distinct', Rule::exists('animals', 'id')
                ->where('farm_id', $farmId)->where('status', AnimalStatus::Active->value)->whereNull('deleted_at')],
            'animals.*.price' => ['required', 'decimal:0,2', 'min:1', 'max:9999999999.99'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $total = Money::sum(array_column((array) $this->input('animals'), 'price'));

            if (Money::compare($this->input('selling_costs'), $total) > 0) {
                $validator->errors()->add('selling_costs', __('Selling costs cannot be more than the sale price.'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_id.exists' => __('Choose an active party who buys animals.'),
            'animals.required' => __('Add at least one animal.'),
            'animals.*.animal_id.exists' => __('Only active animals can be sold.'),
            'animals.*.animal_id.distinct' => __('The same animal is chosen twice.'),
            'animals.*.price.min' => __('Enter the price of each animal.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['animals.*.animal_id' => __('animal'), 'animals.*.price' => __('price')];
    }
}
