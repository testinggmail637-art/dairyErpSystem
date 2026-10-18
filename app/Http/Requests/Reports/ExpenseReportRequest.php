<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/** Filters of the expense reports. Defaults: the current month. */
class ExpenseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
            'month' => $this->input('month') ?: now()->format('Y-m'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'month' => ['required', 'date_format:Y-m'],
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
