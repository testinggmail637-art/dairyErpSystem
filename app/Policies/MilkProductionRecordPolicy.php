<?php

namespace App\Policies;

use App\Models\MilkProductionRecord;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Milk entries (spec 4.5): milk_production.edit changes/deletes any entry (the spec has no separate delete
 * permission); users with only milk_production.create (herd staff) change their own entries on the same day.
 */
class MilkProductionRecordPolicy
{
    use OwnEntrySameDay;

    public function viewAny(User $user): bool
    {
        return $user->can('milk_production.view');
    }

    public function create(User $user): bool
    {
        return $user->can('milk_production.create');
    }

    public function update(User $user, MilkProductionRecord $record): Response
    {
        return $this->decide($user, $record);
    }

    public function delete(User $user, MilkProductionRecord $record): Response
    {
        return $this->decide($user, $record);
    }

    protected function lockReason(Model $record): ?string
    {
        return null; // nothing locks a milk entry in v1
    }

    private function decide(User $user, MilkProductionRecord $record): Response
    {
        return $this->mayChange($user, $record, 'milk_production', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }
}
