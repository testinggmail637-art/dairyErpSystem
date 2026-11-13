<?php

namespace App\Http\Requests\Herd;

use App\Http\Requests\Concerns\RequiresReason;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Give a vaccine to one or many animals; changing one needs a reason (it may change or remove an expense). */
class VaccinationRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('health.manage');
    }

    protected function prepareForValidation(): void
    {
        $cost = str_replace([',', ' ', '₹'], '', trim((string) $this->input('cost')));

        $this->merge([
            'animal_ids' => array_values(array_unique(array_filter((array) $this->input('animal_ids')))),
            'cost' => $cost === '' ? '0' : $cost,
            'record_as_expense' => $this->boolean('record_as_expense'),
            'next_due_on' => $this->input('next_due_on') ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $editing = $this->isMethod('PUT');
        $withExpense = $this->boolean('record_as_expense') && is_numeric($this->input('cost')) && $this->input('cost') > 0;

        return [
            'animal_ids' => ['required', 'array', 'min:1', $editing ? 'max:1' : 'max:200'],
            'animal_ids.*' => ['integer', Rule::exists('animals', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'vaccine_type_id' => ['required', 'integer', Rule::exists('vaccine_types', 'id')->where('farm_id', $farmId)
                ->when(! $editing, fn ($rule) => $rule->where('is_active', true))],
            'given_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'next_due_on' => ['nullable', 'date_format:Y-m-d', 'after:given_on'],
            'cost' => ['required', 'decimal:0,2', 'gte:0', 'max:9999999999.99'],
            'record_as_expense' => ['boolean'],
            'payment_mode_id' => [Rule::requiredIf($withExpense), 'nullable', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ] + ($editing ? $this->reasonRules() : []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'animal_ids.required' => __('Choose at least one animal.'),
            'animal_ids.max' => __('Choose up to :max animals at a time.'),
            'given_on.before_or_equal' => __('The date cannot be in the future.'),
            'next_due_on.after' => __('The next due date must be after the date given.'),
            'cost.decimal' => __('Enter the cost in rupees with up to 2 decimals.'),
            'payment_mode_id.required' => __('Choose how the cost was paid, or untick "Also record as expense".'),
        ];
    }
}
