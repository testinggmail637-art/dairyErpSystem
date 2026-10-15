<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('roles.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower(trim((string) $this->input('name'))),
            'permissions' => array_values((array) $this->input('permissions', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        // Editing a role is audit-logged and needs a reason (spec 4.4).
        $reason = $role instanceof Role ? $this->reasonRules() : [];

        return $reason + [
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Use lower-case letters, numbers and underscores, starting with a letter (e.g. milk_supervisor).'),
        ];
    }
}
