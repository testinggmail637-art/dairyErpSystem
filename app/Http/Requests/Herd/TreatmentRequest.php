<?php

namespace App\Http\Requests\Herd;

use App\Http\Requests\Concerns\RequiresReason;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / change a treatment (spec Phase 5). Changing one needs a reason: it may change or remove an expense. */
class TreatmentRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('health.manage');
    }

    protected function prepareForValidation(): void
    {
        $cost = str_replace([',', ' ', '₹'], '', trim((string) $this->input('cost')));
        $trim = fn (string $key) => trim((string) $this->input($key)) ?: null;

        $this->merge([
            'cost' => $cost === '' ? '0' : $cost,
            'record_as_expense' => $this->boolean('record_as_expense'),
            'diagnosis' => $trim('diagnosis'),
            'medicine' => $trim('medicine'),
            'dose' => $trim('dose'),
            'vet_name' => $trim('vet_name'),
            'notes' => $trim('notes'),
            'milk_withdrawal_until' => $this->input('milk_withdrawal_until') ?: null,
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
            'animal_id' => ['required', 'integer', Rule::exists('animals', 'id')->where('farm_id', $farmId)->whereNull('deleted_at')],
            'treatment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'diagnosis' => ['required', 'string', 'max:255'],
            'medicine' => ['nullable', 'string', 'max:255'],
            'dose' => ['nullable', 'string', 'max:100'],
            'vet_name' => ['nullable', 'string', 'max:150'],
            'cost' => ['required', 'decimal:0,2', 'gte:0', 'max:9999999999.99'],
            'milk_withdrawal_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:treatment_date'],
            'record_as_expense' => ['boolean'],
            'payment_mode_id' => [Rule::requiredIf($withExpense), 'nullable', 'integer',
                Rule::exists('payment_modes', 'id')->where('farm_id', $farmId)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ] + ($this->isMethod('PUT') ? $this->reasonRules() : []);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'treatment_date.before_or_equal' => __('The date cannot be in the future.'),
            'milk_withdrawal_until.after_or_equal' => __('Milk withdrawal must end on or after the treatment date.'),
            'cost.decimal' => __('Enter the cost in rupees with up to 2 decimals.'),
            'payment_mode_id.required' => __('Choose how the cost was paid, or untick "Also record as expense".'),
        ];
    }
}
