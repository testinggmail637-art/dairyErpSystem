<?php

namespace App\Http\Requests\Settings;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
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

        return [
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
