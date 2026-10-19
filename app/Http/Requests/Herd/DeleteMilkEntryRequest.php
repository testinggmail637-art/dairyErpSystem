<?php

namespace App\Http\Requests\Herd;

use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Deleting a milk entry needs a reason for the audit log (spec 4.4); who may delete is the policy's job. */
class DeleteMilkEntryRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('record'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
