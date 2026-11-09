<?php

namespace App\Http\Requests\Loans;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\RequiresReason;
use Illuminate\Foundation\Http\FormRequest;

/** Close a loan (loans.manage) or re-open it (owner only, spec 7.4A) — both with a reason for the audit log. */
class CloseLoanRequest extends FormRequest
{
    use RequiresReason;

    public function authorize(): bool
    {
        $reopen = $this->route()?->getName() === 'loans.reopen';

        return $this->user()->can('loans.manage') && (! $reopen || $this->user()->hasRole(RoleName::Owner->value));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->reasonRules();
    }
}
