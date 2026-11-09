<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\LoanTransaction;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use App\Services\LoanPaymentService;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Loan entries (spec 4.5, 7.4A): loan_payments.edit changes any entry, create-only users their own on the same day —
 * and only the latest entry of an open loan can change at all.
 */
class LoanTransactionPolicy
{
    use OwnEntrySameDay;

    public function update(User $user, LoanTransaction $txn): Response
    {
        return $this->decide($user, $txn);
    }

    public function delete(User $user, LoanTransaction $txn): Response
    {
        return $this->decide($user, $txn);
    }

    protected function lockReason(Model $record): ?string
    {
        /** @var LoanTransaction $record */
        if ($record->loan->status === LoanStatus::Closed) {
            return __('The loan is closed.');
        }

        return app(LoanPaymentService::class)->isLatest($record) ? null : __('Only the latest entry of a loan can be changed.');
    }

    private function decide(User $user, LoanTransaction $txn): Response
    {
        if ($reason = $this->lockReason($txn)) {
            return Response::deny($reason);
        }

        return $this->mayChange($user, $txn, 'loan_payments', 'edit')
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }
}
