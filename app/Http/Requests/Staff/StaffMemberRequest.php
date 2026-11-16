<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\RequiresReason;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit a staff member (spec Phase 6). Edits are audit-logged and need a reason (salary is critical). */
class StaffMemberRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('staff.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => User::normalizePhone($this->input('phone')),
            'designation' => trim((string) $this->input('designation')),
            'monthly_salary' => str_replace([',', ' ', '₹'], '', trim((string) $this->input('monthly_salary'))),
            'user_id' => $this->input('user_id') ?: null,
            'leaving_date' => $this->input('leaving_date') ?: null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $farmId = app(CurrentFarm::class)->id();
        $staff = $this->route('staff');
        $staff = $staff instanceof StaffMember ? $staff : null;

        return ($staff ? $this->reasonRules() : []) + [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'digits:10'],
            'designation' => ['required', 'string', 'max:100'],
            'monthly_salary' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'joining_date' => ['required', 'date_format:Y-m-d'],
            'leaving_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joining_date'],
            // a login user of this farm, linked to one staff record at most
            'user_id' => ['nullable', 'integer',
                Rule::exists('farm_user', 'user_id')->where('farm_id', $farmId),
                Rule::unique('staff_members', 'user_id')->where('farm_id', $farmId)->ignore($staff?->id)],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.digits' => __('Enter a 10-digit mobile number.'),
            'user_id.unique' => __('This login user is already linked to another staff member.'),
            'leaving_date.after_or_equal' => __('The leaving date cannot be before the joining date.'),
        ];
    }
}
