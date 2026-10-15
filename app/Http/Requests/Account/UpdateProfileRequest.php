<?php

namespace App\Http\Requests\Account;

use App\Services\AppearanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A user may change only their own display name and theme here. Email, phone, role and active status
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
            // Own theme override (Settings → Appearance sets the farm default); empty = use the default.
            'theme_mode' => ['nullable', Rule::in(AppearanceService::THEMES)],
        ];
    }
}
