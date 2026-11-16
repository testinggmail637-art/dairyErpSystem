<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/** Start the salary run of a month (this month or earlier). */
class SalaryRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('salary.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m', 'before_or_equal:'.today()->format('Y-m')]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['month.before_or_equal' => __('A salary run can be made for this month or an earlier one.')];
    }
}
