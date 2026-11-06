<?php

namespace App\Policies;

use App\Models\CustomerAdjustment;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer adjustments (spec 5.1 has only customer_adjustments.create): add with that permission; a wrong entry is
 * removed by its own creator on the same day (spec 4.5) and entered again. No edit.
 */
class CustomerAdjustmentPolicy
{
    use OwnEntrySameDay;

    public function create(User $user): bool
    {
        return $user->can('customer_adjustments.create');
    }

    public function delete(User $user, CustomerAdjustment $adjustment): Response
    {
        return $this->mayChange($user, $adjustment, 'customer_adjustments', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only remove your own adjustment, on the day you made it.'));
    }

    protected function lockReason(Model $record): ?string
    {
        return null;
    }
}
