<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A user may change only their own display name here. Email, phone, role and active status
 * are managed by the owner/admin from Settings → Users.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
