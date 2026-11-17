<?php

namespace App\Http\Requests\Reports;

use App\Support\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;

/** Profitability reports: a period (default this financial year); money figures, so the same rights as the dashboard money cards. */
class ProfitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view') && $this->user()->can('dashboard.financial') && $this->user()->can('expenses.view');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: FinancialYear::start()->toDateString(),
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
