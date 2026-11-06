<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/** Sales reports: a period (default this month so far) and an optional export format. */
class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: today()->toDateString(),
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
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
