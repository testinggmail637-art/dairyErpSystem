<?php

namespace App\Http\Requests\Herd;

use App\Enums\AnimalGender;
use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\MilkShift;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\MilkProductionRecord;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Single milk entry (spec 7.3): an active milking female of the farm, one entry per animal / date / shift,
 * 0 … 60 litres, optional fat / SNF. Editing is audit-logged and needs a reason.
 */
class MilkEntryRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $record = $this->record();

        return $record ? $this->user()->can('update', $record) : $this->user()->can('create', MilkProductionRecord::class);
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => ($v = str_replace([',', ' '], '', trim((string) $v))) === '' ? null : $v;

        $this->merge([
            'quantity_litre' => $clean($this->input('quantity_litre')),
            'fat' => $clean($this->input('fat')),
            'snf' => $clean($this->input('snf')),
            'notes' => trim((string) $this->input('notes')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $record = $this->record();
        // a repeated submit of the same form (same client_uuid) must reach the service, which returns the saved entry
        $resubmitted = ! $record && is_string($this->input('client_uuid')) && $this->input('client_uuid') !== ''
            ? MilkProductionRecord::query()->where('client_uuid', $this->input('client_uuid'))->value('id')
            : null;

        return ($record ? $this->reasonRules() : []) + [
            'production_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['required', Rule::enum(MilkShift::class)],
            'animal_id' => ['required', 'integer',
                // an active milking female — or, when editing, the entry's own animal
                Rule::exists('animals', 'id')->where('farm_id', app(CurrentFarm::class)->id())->whereNull('deleted_at')
                    ->where(fn ($q) => $q->where(fn ($q) => $q->where('gender', AnimalGender::Female->value)
                        ->where('lifecycle_stage', LifecycleStage::Milking->value)->where('status', AnimalStatus::Active->value))
                        ->when($record, fn ($q) => $q->orWhere('id', $record->animal_id))),
                Rule::unique('milk_production_records', 'animal_id')
                    ->where('production_date', (string) $this->input('production_date'))
                    ->where('shift', (string) $this->input('shift'))
                    ->ignore($record?->id ?? $resubmitted)],
            'quantity_litre' => ['required', 'decimal:0,2', 'min:0', 'max:'.MilkProductionRecord::MAX_LITRES],
            'fat' => ['nullable', 'decimal:0,2', 'min:0', 'max:20'],
            'snf' => ['nullable', 'decimal:0,2', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'production_date.before_or_equal' => __('Milk cannot be entered for a future date.'),
            'animal_id.exists' => __('Choose an active milking female of this farm.'),
            'animal_id.unique' => __('This animal already has an entry for this date and shift — edit that entry instead.'),
            'quantity_litre.decimal' => __('Enter litres with up to 2 decimals.'),
            'quantity_litre.max' => __('More than :max litres in one shift is not accepted.', ['max' => MilkProductionRecord::MAX_LITRES]),
        ];
    }

    public function record(): ?MilkProductionRecord
    {
        $record = $this->route('record');

        return $record instanceof MilkProductionRecord ? $record : null;
    }
}
