<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwitchFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only an active farm the user is linked to.
            'farm_id' => [
                'required',
                'integer',
                Rule::exists('farm_user', 'farm_id')->where('user_id', $this->user()->id),
                Rule::exists('farms', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
        ];
    }
}
