<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/** Animal trading report: purchase-date period, default this financial year. */
class TradingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.view') && $this->user()->can('animal_trading.view');
    }

    protected function prepareForValidation(): void
    {
        $fyStart = today()->month >= 4 ? today()->setDate(today()->year, 4, 1) : today()->setDate(today()->year - 1, 4, 1);

        $this->merge([
            'from' => $this->input('from') ?: $fyStart->toDateString(),
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
