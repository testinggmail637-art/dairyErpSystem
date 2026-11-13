<?php

namespace App\Http\Requests\Herd;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Undoing a calving (lactation back, calf removed) needs a reason for the audit log. */
class DeleteCalvingRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('breeding.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
