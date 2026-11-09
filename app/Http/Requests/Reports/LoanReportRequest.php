<?php

namespace App\Http\Requests\Reports;

use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Loan reports (spec 7.4A): "as on" date for the summary, a period for the others (default this financial year). */
class LoanReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view') && $this->user()->can('loans.view');
    }

    protected function prepareForValidation(): void
    {
        $fyStart = today()->month >= 4 ? today()->setDate(today()->year, 4, 1) : today()->setDate(today()->year - 1, 4, 1);
        $due = $this->route('report') === 'due';

        $this->merge([
            'date' => $this->input('date') ?: today()->toDateString(),
            'from' => $this->input('from') ?: ($due ? today()->startOfMonth() : $fyStart)->toDateString(),
            'to' => $this->input('to') ?: ($due ? today()->endOfMonth() : today())->toDateString(),
            'loan_id' => $this->input('loan_id') ?: null,
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
            'loan_id' => ['nullable', 'integer', Rule::exists('loans', 'id')->where('farm_id', app(CurrentFarm::class)->id())],
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
