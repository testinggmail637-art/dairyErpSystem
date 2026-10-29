<?php

namespace App\Http\Requests\Settings;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\RequiresReason;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit a user from Settings → Users.
 *
 * Rules beyond the fields: only an owner may give or take the owner role (or edit an owner), a non-owner
 * may only give roles within his own permissions, at least one active owner must remain, nobody can
 * deactivate themselves, and farms are limited to the editor's own farms.
 */
class UserRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $target = $this->targetUser();

        // Admins (users.manage without being owner) cannot edit an owner account.
        return $target === null || ! $target->hasRole(RoleName::Owner->value) || $this->editorIsOwner();
    }

    protected function prepareForValidation(): void
    {
        $email = strtolower(trim((string) $this->input('email')));

        $this->merge([
            'email' => $email === '' ? null : $email,
            'phone' => User::normalizePhone($this->input('phone')),
            'is_active' => $this->boolean('is_active'),
            'roles' => array_values((array) $this->input('roles', [])),
            'farms' => array_map('intval', array_values((array) $this->input('farms', []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = $this->targetUser()?->getKey();

        // Editing an existing user is audit-logged and needs a reason (spec 4.4).
        $reason = $ignore ? $this->reasonRules() : [];

        return $reason + [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:150', Rule::unique('users', 'email')->ignore($ignore)],
            'phone' => ['nullable', 'required_without:email', 'digits:10', Rule::unique('users', 'phone')->ignore($ignore)],
            'password' => [$ignore ? 'nullable' : 'required', 'string', 'min:8', 'max:100', 'confirmed'],
            'is_active' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'farms' => ['required', 'array', 'min:1'],
            'farms.*' => ['integer', Rule::in($this->editorFarmIds())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('Enter an email or a phone number.'),
            'phone.required_without' => __('Enter an email or a phone number.'),
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'farms.*.in' => __('You can only give access to your own farms.'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $target = $this->targetUser();
            $givesOwner = in_array(RoleName::Owner->value, $this->input('roles', []), true);
            $isOwner = $target?->hasRole(RoleName::Owner->value) ?? false;

            if ($givesOwner && ! $isOwner && ! $this->editorIsOwner()) {
                $validator->errors()->add('roles', __('Only an owner can give the owner role.'));
            }

            // A non-owner (e.g. admin) may not hand out a role stronger than his own — that would let him
            // reach farms.manage, roles.manage, … through a custom role. Roles the user already has may stay.
            if (! $this->editorIsOwner()) {
                $mine = $this->user()->getAllPermissions()->pluck('name');
                $newRoles = array_diff($this->input('roles', []), $target?->roles->pluck('name')->all() ?? [], [RoleName::Owner->value]);
                $stronger = Role::query()->whereIn('name', $newRoles)->with('permissions:id,name')->get()
                    ->first(fn (Role $role) => $role->permissions->pluck('name')->diff($mine)->isNotEmpty());

                if ($stronger) {
                    $validator->errors()->add('roles', __('You cannot give the role ":role": it has permissions you do not have.', ['role' => role_label($stronger->name)]));
                }
            }

            if ($target === null) {
                return;
            }

            if ($target->is($this->user()) && ! $this->boolean('is_active')) {
                $validator->errors()->add('is_active', __('You cannot deactivate your own account.'));
            }

            $staysActiveOwner = $givesOwner && $this->boolean('is_active');

            if ($isOwner && $target->is_active && ! $staysActiveOwner
                && ! app(UserService::class)->otherActiveOwnersExist($target)) {
                $validator->errors()->add('roles', __('At least one active owner must remain.'));
            }
        }];
    }

    public function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    /** @return list<int> */
    public function editorFarmIds(): array
    {
        return $this->user()->farms()->pluck('farms.id')->map(fn ($id) => (int) $id)->all();
    }

    private function editorIsOwner(): bool
    {
        return $this->user()->hasRole(RoleName::Owner->value);
    }
}
