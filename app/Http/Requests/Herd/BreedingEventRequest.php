<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalGender;
use App\Enums\BreedingEventType;
use App\Enums\PregnancyResult;
use App\Http\Requests\Concerns\RequiresReason;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Heat / AI / natural service / pregnancy check / abortion of a female animal; changing one needs a reason. */
class BreedingEventRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('breeding.manage');
    }

    protected function prepareForValidation(): void
    {
        $cost = str_replace([',', ' ', '₹'], '', trim((string) $this->input('cost')));
        $trim = fn (string $key) => trim((string) $this->input($key)) ?: null;

        $this->merge([
            'cost' => $cost === '' ? '0' : $cost,
            'record_as_expense' => $this->boolean('record_as_expense'),
            'sire_info' => $trim('sire_info'),
            'technician' => $trim('technician'),
            'notes' => $trim('notes'),
            'result' => $this->input('result') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $withExpense = $this->boolean('record_as_expense') && is_numeric($this->input('cost')) && $this->input('cost') > 0;

        return [
            'animal_id' => ['required', 'integer', Rule::exists('animals', 'id')->where('farm_id', $farmId)
                ->where('gender', AnimalGender::Female->value)->whereNull('deleted_at')],
            'event_type' => ['required', Rule::enum(BreedingEventType::class)],
            'event_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'result' => ['nullable', Rule::requiredIf($this->input('event_type') === BreedingEventType::PregnancyCheck->value), Rule::enum(PregnancyResult::class)],
            'sire_info' => ['nullable', 'string', 'max:150'],
            'technician' => ['nullable', 'string', 'max:150'],
            'cost' => ['required', 'decimal:0,2', 'gte:0', 'max:9999999999.99'],
            'record_as_expense' => ['boolean'],
            'payment_mode_id' => [Rule::requiredIf($withExpense), 'nullable', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ] + ($this->isMethod('PUT') ? $this->reasonRules() : []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'animal_id.exists' => __('Choose a female animal of this farm.'),
            'event_date.before_or_equal' => __('The date cannot be in the future.'),
            'result.required' => __('Choose the result of the pregnancy check.'),
            'cost.decimal' => __('Enter the cost in rupees with up to 2 decimals.'),
            'payment_mode_id.required' => __('Choose how the cost was paid, or untick "Also record as expense".'),
        ];
    }
}
