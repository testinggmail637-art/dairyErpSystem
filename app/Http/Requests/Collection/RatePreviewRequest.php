<?php

namespace App\Http\Requests\Collection;

use Illuminate\Foundation\Http\FormRequest;

/** Rate preview calculator: fat / SNF / litres for one chart. */
class RatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rate_charts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fat' => ['nullable', 'decimal:0,2', 'between:0,20'],
            'snf' => ['nullable', 'decimal:0,2', 'between:0,20'],
            'qty' => ['nullable', 'decimal:0,2', 'gt:0', 'max:500'],
        ];
    }
}
