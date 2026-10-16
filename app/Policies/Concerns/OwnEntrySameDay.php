<?php

namespace App\Policies\Concerns;

use App\Models\Farm;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Model;

/**
 * Edit rules for staff (spec 4.5), shared by every entry type (expenses, milk entries, collections …):
 * - users with "{module}.edit" / "{module}.delete" may change any entry that is not locked;
 * - users with only "{module}.create" may change their OWN entries, on the SAME calendar day (IST) — unless the
 *   farm setting staff_same_day_edit_only is off, then their own entries on any day;
 * - locked entries (see lockReason()) cannot be changed by anyone.
 */
trait OwnEntrySameDay
{
    /** Why the record is locked, or null. Override per model (e.g. "Included in Bill BILL/2026-27/0012"). */
    abstract protected function lockReason(Model $record): ?string;

    protected function mayChange(User $user, Model $record, string $module, string $ability): bool
    {
        if ($this->lockReason($record) !== null) {
            return false;
        }

        if ($user->can("{$module}.{$ability}")) {
            return true;
        }

        return $this->isOwnEditableEntry($user, $record, $module);
    }

    /** A create-only user's own entry that is still within the edit window. */
    protected function isOwnEditableEntry(User $user, Model $record, string $module): bool
    {
        if (! $user->can("{$module}.create") || (int) $record->getAttribute('created_by') !== (int) $user->getKey()) {
            return false;
        }

        $farm = app(CurrentFarm::class)->farm() ?? Farm::find($record->getAttribute('farm_id'));
        $sameDayOnly = (bool) ($farm?->setting('staff_same_day_edit_only') ?? true);

        return ! $sameDayOnly || $record->getAttribute('created_at')?->timezone(config('app.timezone'))->isToday();
    }
}
