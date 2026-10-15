<?php

namespace App\Http\Requests\Concerns;

use App\Support\AuditReason;

/**
 * For Form Requests that edit or delete a critical record (spec 4.4): a required `reason` (min 3 chars)
 * that ends up in the audit log entry. Merge reasonRules() into rules() where a reason is needed.
 */
trait RequiresReason
{
    /**
     * @return array<string, list<string>>
     */
    protected function reasonRules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    protected function passedValidation(): void
    {
        app(AuditReason::class)->set($this->input('reason'));
    }
}
