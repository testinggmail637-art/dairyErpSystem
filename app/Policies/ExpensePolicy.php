<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\OwnEntrySameDay;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see / change an expense (spec 4.5). Found by Laravel's policy discovery (App\Policies\{Model}Policy).
 */
class ExpensePolicy
{
    use OwnEntrySameDay;

    /** The list: everyone who sees expenses, and create-only staff (they see only their own entries of today). */
    public function viewAny(User $user): bool
    {
        return $user->canAny(['expenses.view', 'expenses.create']);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view') || $this->isOwnEditableEntry($user, $expense, 'expenses');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): Response
    {
        return $this->decide($user, $expense, 'edit');
    }

    public function delete(User $user, Expense $expense): Response
    {
        return $this->decide($user, $expense, 'delete');
    }

    protected function lockReason(Model $record): ?string
    {
        return $record instanceof Expense && $record->isAutoCreated()
            ? __('Created automatically — change it from where it came from.')
            : null;
    }

    private function decide(User $user, Expense $expense, string $ability): Response
    {
        if ($reason = $this->lockReason($expense)) {
            return Response::deny($reason);
        }

        return $this->mayChange($user, $expense, 'expenses', $ability)
            ? Response::allow()
            : Response::deny(__('You can only change your own entries, on the day you made them.'));
    }

    /** Lock reason for the screens (lock icon + text). */
    public static function lockedBecause(Expense $expense): ?string
    {
        return (new self)->lockReason($expense);
    }
}
