<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** "Generate sheet" button: managers who see all routes (deliveries.view_all_routes). */
class GenerateDeliveriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deliveries.view_all_routes');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d']];
    }
}
