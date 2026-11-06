<?php

namespace App\Http\Requests\Sales;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SkipDeliveryRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('delivery'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['skip_reason' => ['required', 'string', 'min:2', 'max:255']];
    }
}
