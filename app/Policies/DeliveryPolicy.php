<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Deliveries (spec 5.2): a delivery person works only on rows of routes assigned to him; users with
 * deliveries.view_all_routes on every route. Billed rows are locked; future rows cannot be marked yet.
 */
class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.view') && $this->onUsersRoute($user, $delivery);
    }

    public function update(User $user, Delivery $delivery): Response
    {
        if (! $user->can('deliveries.update') || ! $this->onUsersRoute($user, $delivery)) {
            return Response::denyAsNotFound();
        }

        if ($delivery->isBilled()) {
            return Response::deny(__('This delivery is included in a bill and is locked.'));
        }

        return $delivery->delivery_date->isAfter(today())
            ? Response::deny(__('A delivery cannot be marked before its date.'))
            : Response::allow();
    }

    /** "Collect payment" from a sheet row: the customer of a row the user may see. */
    public function collectPayment(User $user, Delivery $delivery): bool
    {
        return $user->can('customer_payments.create') && $this->onUsersRoute($user, $delivery);
    }

    private function onUsersRoute(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.view_all_routes')
            || ($delivery->route_id !== null && $delivery->route()->where('delivery_user_id', $user->id)->exists());
    }
}
