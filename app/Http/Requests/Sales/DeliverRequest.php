<?php

namespace App\Http\Requests\Sales;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Mark a sheet row delivered, or change its delivered litres (may be above planned). 0 litres = use Skip. */
class DeliverRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('delivery'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['delivered_qty' => str_replace([',', ' '], '', trim((string) $this->input('delivered_qty')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['delivered_qty' => ['required', 'decimal:0,2', 'gt:0', 'max:99999.99']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['delivered_qty.gt' => __('Enter litres above 0, or use Skip.')];
    }
}
