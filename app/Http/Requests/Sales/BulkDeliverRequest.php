<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** Admin grid: mark the ticked pending rows delivered with their planned litres. */
class BulkDeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deliveries.update') && $this->user()->can('deliveries.view_all_routes');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ids.required' => __('Tick the deliveries to mark first.')];
    }
}
