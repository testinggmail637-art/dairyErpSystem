<?php

namespace App\Http\Requests\Collection;

use App\Enums\MilkShift;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One farmer's milk in a shift (spec Phase 4): litres 0–500, readings in range, a reason when rejected. */
class CollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('collections.create');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn (string $key) => ($v = str_replace([',', ' '], '', trim((string) $this->input($key)))) === '' ? null : $v;

        $this->merge([
            'quantity_litre' => $clean('quantity_litre'),
            'fat' => $clean('fat'),
            'snf' => $clean('snf'),
            'clr' => $clean('clr'),
            'quality_status' => $this->input('quality_status') ?: QualityStatus::Accepted->value,
            'accept_below' => $this->boolean('accept_below'),
            'rejection_reason' => trim((string) $this->input('rejection_reason')) ?: null,
            'client_uuid' => $this->input('client_uuid') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'shift' => ['required', Rule::enum(MilkShift::class)],
            'farmer_id' => ['required', 'integer', Rule::exists('farmers', 'id')->where('farm_id', app(CurrentFarm::class)->id())
                ->where('is_active', true)->whereNull('deleted_at')],
            'milk_type' => ['required', Rule::enum(MilkType::class)],
            'quantity_litre' => ['required', 'decimal:0,2', 'gt:0', 'max:500'],
            'fat' => ['nullable', 'decimal:0,2', 'between:0.1,15'],
            'snf' => ['nullable', 'decimal:0,2', 'between:0.1,15'],
            'clr' => ['nullable', 'decimal:0,2', 'between:0,40'],
            'quality_status' => ['required', Rule::enum(QualityStatus::class)],
            'rejection_reason' => ['nullable', 'required_if:quality_status,'.QualityStatus::Rejected->value, 'string', 'max:255'],
            'accept_below' => ['boolean'],
            'client_uuid' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'collection_date.before_or_equal' => __('The date cannot be in the future.'),
            'farmer_id.required' => __('Enter the farmer code.'),
            'farmer_id.exists' => __('This farmer is not active here.'),
            'quantity_litre.gt' => __('Enter the litres.'),
            'quantity_litre.max' => __('More than 500 L from one farmer in a shift — check the litres.'),
            'fat.between' => __('Fat must be between 0.1 and 15.'),
            'snf.between' => __('SNF must be between 0.1 and 15.'),
            'rejection_reason.required_if' => __('Say why the milk is rejected.'),
        ];
    }
}
