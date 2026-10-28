<?php

namespace App\Http\Requests\Herd;

use App\Enums\MilkShift;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use App\Services\MilkProductionService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The date + shift grid (spec 7.3): qty[animal_id] = litres (0 … 60, 2 decimals); blanks are skipped.
 * Only grid animals are accepted; changing a saved entry follows the edit rules (spec 4.5) and needs a reason
 * because milk entries are audit-logged (spec 4.4).
 */
class BulkMilkEntryRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('create', MilkProductionRecord::class);
    }

    protected function prepareForValidation(): void
    {
        $qty = [];
        foreach ((array) $this->input('qty', []) as $animalId => $value) {
            $value = str_replace([',', ' '], '', trim((string) $value));
            $qty[$animalId] = $value === '' ? null : $value;
        }

        $this->merge(['qty' => $qty, 'reason' => trim((string) $this->input('reason')) ?: null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'production_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['required', Rule::enum(MilkShift::class)],
            'qty' => ['array'],
            'qty.*' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.MilkProductionRecord::MAX_LITRES],
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'production_date.before_or_equal' => __('Milk cannot be entered for a future date.'),
            'qty.*.decimal' => __('Enter litres with up to 2 decimals.'),
            'qty.*.min' => __('Litres cannot be negative.'),
            'qty.*.max' => __('More than :max litres in one shift is not accepted.', ['max' => MilkProductionRecord::MAX_LITRES]),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $quantities = array_filter($this->validated('qty', []), fn ($q) => $q !== null);
            if ($quantities === []) {
                $validator->errors()->add('qty', __('Enter the litres of at least one animal.'));

                return;
            }

            $ids = array_map('intval', array_keys($quantities));
            $existing = app(MilkProductionService::class)->entriesFor($this->validated('production_date'), $this->shift(), $ids);
            $milking = Animal::query()->milking()->whereIn('id', $ids)->pluck('id')->all();
            $changedSaved = false;

            foreach ($quantities as $animalId => $litres) {
                $record = $existing->get((int) $animalId);

                if ($record === null && ! in_array((int) $animalId, $milking, true)) {
                    $validator->errors()->add("qty.{$animalId}", __('This animal is not in milk entry (not an active milking female of this farm).'));
                } elseif ($record !== null && Money::compare($record->quantity_litre, $litres) !== 0) {
                    $changedSaved = true;
                    if ($this->user()->cannot('update', $record)) {
                        $validator->errors()->add("qty.{$animalId}", __('You can only change your own entries, on the day you made them.'));
                    }
                }
            }

            if ($changedSaved && $this->input('reason') === null) {
                $validator->errors()->add('reason', __('You changed saved entries — write a reason (it goes to the audit log).'));
            }
        }];
    }

    public function shift(): MilkShift
    {
        return MilkShift::from($this->validated('shift'));
    }
}
