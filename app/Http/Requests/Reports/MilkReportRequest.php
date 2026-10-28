<?php

namespace App\Http\Requests\Reports;

use App\Enums\AnimalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters of the milk production reports. Defaults: today (daily), this month (monthly, animal-wise), all milk types. */
class MilkReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'date' => $this->input('date') ?: today()->toDateString(),
            'from' => $this->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: today()->toDateString(),
            'month' => $this->input('month') ?: now()->format('Y-m'),
            'milk_type' => $this->input('milk_type') ?: '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'month' => ['required', 'date_format:Y-m'],
            'milk_type' => ['nullable', 'string', Rule::in(['', ...AnimalType::values()])],
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
