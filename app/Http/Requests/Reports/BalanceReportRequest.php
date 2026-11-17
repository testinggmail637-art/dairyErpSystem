<?php

namespace App\Http\Requests\Reports;

use App\Services\BalanceReportService;
use Illuminate\Foundation\Http\FormRequest;

/** Receivables & payables reports: one "as of" date (default today); each report also needs its module permission. */
class BalanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view')
            && $this->user()->can(BalanceReportService::PERMISSIONS[$this->route('report')]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['date' => $this->input('date') ?: today()->toDateString()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
