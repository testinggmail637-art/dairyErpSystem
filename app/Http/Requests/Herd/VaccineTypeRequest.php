<?php

namespace App\Http\Requests\Herd;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / change a vaccine: name (unique on the farm), repeat interval in days, active. */
class VaccineTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('health.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'interval_days' => $this->input('interval_days') ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('vaccine_types', 'name')
                ->where('farm_id', app(CurrentFarm::class)->id())->ignore($this->route('vaccine_type'))],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
