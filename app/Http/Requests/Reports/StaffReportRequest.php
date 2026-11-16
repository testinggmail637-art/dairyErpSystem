<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

/** Staff registers: a month for the attendance register, a period for the salary register (needs salary.manage). */
class StaffReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('reports.view') && $user->can('staff.view')
            && ($this->route('report') !== 'salary' || $user->can('salary.manage'));
    }

    protected function prepareForValidation(): void
    {
        $fyStart = today()->month >= 4 ? today()->setDate(today()->year, 4, 1) : today()->setDate(today()->year - 1, 4, 1);

        $this->merge([
            'month' => $this->input('month') ?: today()->format('Y-m'),
            'from' => $this->input('from') ?: $fyStart->toDateString(),
            'to' => $this->input('to') ?: today()->toDateString(),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'format' => ['nullable', 'in:excel,pdf'],
        ];
    }
}
