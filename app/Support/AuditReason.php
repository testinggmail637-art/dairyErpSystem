<?php

namespace App\Support;

/**
 * The reason the user typed for the current edit/delete (spec 4.4). Set by Form Requests that use
 * RequiresReason; copied into every activity log entry written during the same request.
 */
class AuditReason
{
    private ?string $reason = null;

    public function set(?string $reason): void
    {
        $reason = $reason === null ? null : trim($reason);
        $this->reason = $reason === '' ? null : $reason;
    }

    public function get(): ?string
    {
        return $this->reason;
    }
}
