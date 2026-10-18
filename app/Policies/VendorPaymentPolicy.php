<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorPayment;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Vendor payments (spec 4.5): vendor_payments.edit changes/deletes any payment (the spec has no separate delete
 * permission); users with only vendor_payments.create change their own payment on the same day.
 */
class VendorPaymentPolicy
{
    use OwnEntrySameDay;

    public function create(User $user): bool
    {
        return $user->can('vendor_payments.create');
    }

    public function update(User $user, VendorPayment $payment): Response
    {
        return $this->decide($user, $payment);
    }

    public function delete(User $user, VendorPayment $payment): Response
    {
        return $this->decide($user, $payment);
    }

    protected function lockReason(Model $record): ?string
    {
        return null; // nothing locks a vendor payment in v1
    }

    private function decide(User $user, VendorPayment $payment): Response
    {
        return $this->mayChange($user, $payment, 'vendor_payments', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }
}
