<?php

namespace App\Http\Requests\Staff;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting an advance needs a reason for the audit log (spec 4.4). */
class DeleteStaffAdvanceRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('salary.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
