<?php

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer payments (spec 4.5): customer_payments.edit changes / deletes any payment; users with only
 * customer_payments.create (e.g. the delivery person) their own payment on the same day.
 */
class CustomerPaymentPolicy
{
    use OwnEntrySameDay;

    public function create(User $user): bool
    {
        return $user->can('customer_payments.create');
    }

    public function update(User $user, CustomerPayment $payment): Response
    {
        return $this->decide($user, $payment);
    }

    public function delete(User $user, CustomerPayment $payment): Response
    {
        return $this->decide($user, $payment);
    }

    protected function lockReason(Model $record): ?string
    {
        return null; // allocations are rebuilt on every change, so a payment is never locked
    }

    private function decide(User $user, CustomerPayment $payment): Response
    {
        return $this->mayChange($user, $payment, 'customer_payments', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }
}
