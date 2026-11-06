<?php

namespace App\Http\Requests\Sales;

use App\Support\FinancialYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('customers.view');
    }

    /** Opens on the current financial year (April → today); "All time" sends all=1. */
    protected function prepareForValidation(): void
    {
        if (! $this->hasAny(['from', 'to', 'all'])) {
            $this->merge(['from' => FinancialYear::start()->toDateString(), 'to' => now()->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'all' => ['nullable', 'in:1'],
            'format' => ['nullable', Rule::in(['excel', 'pdf'])],
        ];
    }
}
