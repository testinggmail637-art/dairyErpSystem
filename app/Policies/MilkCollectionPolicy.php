<?php

namespace App\Policies;

use App\Models\MilkCollection;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Milk collections (spec 4.5): collections.edit changes / deletes any entry; an operator with only collections.create
 * changes their own entries on the same day. An entry in a farmer payment statement is locked for everyone.
 */
class MilkCollectionPolicy
{
    use OwnEntrySameDay;

    public function update(User $user, MilkCollection $collection): Response
    {
        return $this->decide($user, $collection);
    }

    public function delete(User $user, MilkCollection $collection): Response
    {
        return $this->decide($user, $collection);
    }

    protected function lockReason(Model $record): ?string
    {
        return $record->getAttribute('farmer_payment_id') !== null ? __('Included in a farmer payment statement.') : null;
    }

    private function decide(User $user, MilkCollection $collection): Response
    {
        if (($lock = $this->lockReason($collection)) !== null) {
            return Response::deny($lock);
        }

        return $this->mayChange($user, $collection, 'collections', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }
}
