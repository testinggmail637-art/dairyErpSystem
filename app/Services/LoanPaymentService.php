<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\LoanTxnType;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTransaction;
use App\Support\Money;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Payments on instalment loans (spec 7.4A). A payment covers the oldest unpaid instalments — each instalment's
 * interest first, then its principal. The split is suggested here and may be replaced by the bank's figures; it is
 * stored on the transaction. The interest part becomes a paid expense (Finance → Loan Interest); principal never does.
 * Bank charges (penal, late, foreclosure …) are separate `charge` entries → Finance → Bank Charges.
 * Only the latest entry of a loan can be edited or deleted (with a reason).
 */
class LoanPaymentService
{
    /** Types that pay instalments (counted in the schedule status). */
    public const INSTALMENT_TYPES = [LoanTxnType::EmiPayment, LoanTxnType::InterestPayment];

    public function __construct(private readonly FinanceExpenseService $expenses) {}

    /**
     * The schedule with what is paid on each instalment (FIFO by amount: interest, then principal) and its status.
     *
     * @return array{rows: Collection<int, LoanInstallment>, paid_count: int, total: int, interest_paid: string, principal_paid: string, total_paid: string, outstanding: string, next: ?LoanInstallment, remaining_due: string}
     */
    public function state(Loan $loan, ?int $exceptTxnId = null): array
    {
        // Instalment payments drive the schedule status; prepayments (which re-plan the schedule) only add to the totals shown.
        $totals = $loan->transactions()
            ->whereIn('type', [...array_map(fn ($t) => $t->value, self::INSTALMENT_TYPES), LoanTxnType::Prepayment->value])
            ->when($exceptTxnId, fn ($q) => $q->whereKeyNot($exceptTxnId))
            ->selectRaw('COALESCE(SUM(CASE WHEN type <> ? THEN amount ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN type <> ? THEN principal_part ELSE 0 END), 0) AS principal,
                COALESCE(SUM(interest_part), 0) AS interest, COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS prepaid',
                [LoanTxnType::Prepayment->value, LoanTxnType::Prepayment->value, LoanTxnType::Prepayment->value])
            ->first();

        $left = Money::round2((string) $totals->paid);
        $rows = $loan->installments()->get();
        $today = today()->toDateString();
        $schedulePrincipal = '0.00';
        $paidCount = 0;
        $next = null;

        foreach ($rows as $row) {
            $interestPaid = $this->min($left, $row->interest_part);
            $left = Money::sub($left, $interestPaid);
            $principalPaid = $this->min($left, $row->principal_part);
            $left = Money::sub($left, $principalPaid);

            $row->paid_interest = $interestPaid;
            $row->paid_principal = $principalPaid;
            $row->balance_due = Money::sub($row->emi_amount, Money::add($interestPaid, $principalPaid));
            $row->status = match (true) {
                Money::isZero($row->balance_due) => 'paid',
                $row->due_date->toDateString() < $today => 'overdue',
                Money::compare(Money::add($interestPaid, $principalPaid), '0') > 0 => 'partially_paid',
                default => 'due',
            };
            $schedulePrincipal = Money::add($schedulePrincipal, $row->principal_part);

            if ($row->status === 'paid') {
                $paidCount += $row->installment_no > 0 ? 1 : 0;
            } else {
                $next ??= $row;
            }
        }

        return [
            'rows' => $rows,
            'paid_count' => $paidCount,
            'total' => $rows->where('installment_no', '>', 0)->count(),
            'interest_paid' => Money::round2((string) $totals->interest),
            'principal_paid' => Money::add((string) $totals->principal, (string) $totals->prepaid),
            'total_paid' => Money::add((string) $totals->paid, (string) $totals->prepaid),
            'outstanding' => Money::sub($schedulePrincipal, (string) $totals->principal),
            'next' => $next,
            'remaining_due' => Money::sum($rows->pluck('balance_due')),
        ];
    }

    /**
     * List view: paid x / y, outstanding, total paid and next due date for a page of loans — two queries in all.
     *
     * @param  Collection<int, Loan>  $loans
     * @return array<int, array{paid_count: int, total: int, outstanding: string, total_paid: string, next_due: ?string}>
     */
    public function summaries(Collection $loans): array
    {
        $ids = $loans->filter->hasSchedule()->modelKeys();
        if ($ids === []) {
            return [];
        }

        $paid = LoanTransaction::query()->whereIn('loan_id', $ids)
            ->whereIn('type', array_map(fn ($t) => $t->value, self::INSTALMENT_TYPES))
            ->groupBy('loan_id')
            ->selectRaw('loan_id, SUM(amount) AS paid, SUM(principal_part) AS principal')
            ->get()->keyBy('loan_id');

        $out = [];
        LoanInstallment::query()->whereIn('loan_id', $ids)->orderBy('loan_id')->orderBy('installment_no')
            ->get(['loan_id', 'installment_no', 'due_date', 'emi_amount', 'principal_part'])
            ->groupBy('loan_id')
            ->each(function (Collection $rows, int $loanId) use ($paid, &$out) {
                $left = Money::round2((string) ($paid[$loanId]->paid ?? '0'));
                $count = 0;
                $next = null;

                foreach ($rows as $row) {
                    if (Money::compare($left, $row->emi_amount) >= 0) {
                        $left = Money::sub($left, $row->emi_amount);
                        $count += $row->installment_no > 0 ? 1 : 0;
                    } else {
                        $next ??= $row->due_date->toDateString();
                        $left = '0.00';
                    }
                }

                $out[$loanId] = [
                    'paid_count' => $count,
                    'total' => $rows->where('installment_no', '>', 0)->count(),
                    'outstanding' => Money::sub(Money::sum($rows->pluck('principal_part')), (string) ($paid[$loanId]->principal ?? '0')),
                    'total_paid' => Money::round2((string) ($paid[$loanId]->paid ?? '0')),
                    'next_due' => $next,
                ];
            });

        return $out;
    }

    /**
     * Suggested split of $amount over the oldest unpaid instalments.
     *
     * @return array{interest: string, principal: string}
     */
    public function suggestSplit(Loan $loan, string $amount, ?int $exceptTxnId = null): array
    {
        $left = Money::round2($amount);
        $interest = '0.00';
        $principal = '0.00';

        foreach ($this->state($loan, $exceptTxnId)['rows'] as $row) {
            $i = $this->min($left, Money::sub($row->interest_part, $row->paid_interest));
            $left = Money::sub($left, $i);
            $p = $this->min($left, Money::sub($row->principal_part, $row->paid_principal));
            $left = Money::sub($left, $p);
            $interest = Money::add($interest, $i);
            $principal = Money::add($principal, $p);
        }

        return ['interest' => $interest, 'principal' => $principal];
    }

    /**
     * Instalment payment or bank charge; idempotent on client_uuid.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: LoanTransaction, 1: bool}
     */
    public function record(Loan $loan, array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = LoanTransaction::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(function () use ($loan, $data, $uuid) {
                Loan::query()->whereKey($loan->id)->lockForUpdate()->value('id');
                $this->assertOpen($loan);

                $txn = new LoanTransaction(['loan_id' => $loan->id, 'client_uuid' => $uuid]);
                $this->apply($loan, $txn, $data);

                return $txn;
            }), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? LoanTransaction::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(LoanTransaction $txn, array $data): LoanTransaction
    {
        return DB::transaction(function () use ($txn, $data) {
            $loan = $txn->loan;
            Loan::query()->whereKey($loan->id)->lockForUpdate()->value('id');
            $this->assertLatest($txn);
            $this->assertOpen($loan);
            if ($txn->type === LoanTxnType::Prepayment) {
                throw new DomainException(__('A prepayment cannot be edited. Delete it (the schedule goes back) and enter it again.'));
            }
            $this->apply($loan, $txn, $data);

            return $txn;
        });
    }

    public function delete(LoanTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {
            Loan::query()->whereKey($txn->loan_id)->lockForUpdate()->value('id');
            $this->assertLatest($txn);

            if ($txn->type === LoanTxnType::Prepayment) {
                app(LoanRescheduleService::class)->undoPrepayment($txn); // the old schedule comes back
            }

            $expense = $txn->expense;
            $txn->update(['expense_id' => null]);
            $expense?->delete();
            $txn->delete();
        });
    }

    /** The newest entry of its loan (by date, then id) — the only one that may change. */
    public function isLatest(LoanTransaction $txn): bool
    {
        return ! LoanTransaction::query()->where('loan_id', $txn->loan_id)->whereKeyNot($txn->id)
            ->where(fn ($q) => $q->where('txn_date', '>', $txn->txn_date->toDateString())
                ->orWhere(fn ($q) => $q->where('txn_date', $txn->txn_date->toDateString())->where('id', '>', $txn->id)))
            ->exists();
    }

    /** @param  array<string, mixed>  $data */
    private function apply(Loan $loan, LoanTransaction $txn, array $data): void
    {
        $amount = Money::round2($data['amount']);
        $charge = ($data['kind'] ?? 'payment') === 'charge';

        if ($charge) {
            $interest = '0.00';
            $principal = '0.00';
            $type = LoanTxnType::Charge;
        } else {
            $due = $this->state($loan, $txn->exists ? $txn->id : null)['remaining_due'];
            if (Money::compare($amount, $due) > 0) {
                throw new DomainException(__('This is more than everything still due (:due). Use "Prepayment" to pay principal early.', ['due' => inr($due)]));
            }

            if (filled($data['interest_part'] ?? null) || filled($data['principal_part'] ?? null)) {
                $interest = Money::round2($data['interest_part'] ?? '0');
                $principal = Money::round2($data['principal_part'] ?? '0');
                if (Money::compare(Money::add($interest, $principal), $amount) !== 0) {
                    throw new DomainException(__('Interest + principal must equal the amount paid.'));
                }
            } else {
                ['interest' => $interest, 'principal' => $principal] = $this->suggestSplit($loan, $amount, $txn->exists ? $txn->id : null);
            }

            $type = Money::isZero($principal) ? LoanTxnType::InterestPayment : LoanTxnType::EmiPayment;
        }

        $txn->fill([
            'txn_date' => $data['txn_date'],
            'type' => $type,
            'amount' => $amount,
            'interest_part' => $interest,
            'principal_part' => $principal,
            'payment_mode_id' => $data['payment_mode_id'],
            'reference_no' => $data['reference_no'] ?? null,
            'note' => $data['note'] ?? null,
        ])->save();

        $expense = $this->expenses->sync($txn, $charge ? $amount : $interest, $data['txn_date'],
            $charge ? FinanceExpenseService::CHARGES : FinanceExpenseService::INTEREST, (int) $data['payment_mode_id'], $loan->lender_name,
            ($charge ? __('Bank charge') : __('Loan interest')).' · '.$loan->loan_no.(filled($data['note'] ?? null) ? ' · '.$data['note'] : ''), $loan->id);

        if ($txn->expense_id !== $expense?->id) {
            $txn->update(['expense_id' => $expense?->id]);
        }
    }

    private function assertOpen(Loan $loan): void
    {
        if ($loan->status === LoanStatus::Closed) {
            throw new DomainException(__('This loan is closed. Re-open it to add or change entries.'));
        }
        if (! $loan->hasSchedule()) {
            throw new DomainException(__('Overdraft, cash credit, KCC and card entries are recorded on their own account page.'));
        }
    }

    private function assertLatest(LoanTransaction $txn): void
    {
        if (! $this->isLatest($txn)) {
            throw new DomainException(__('Only the latest entry of a loan can be changed or deleted. Change the newer ones first.'));
        }
    }

    private function min(string $a, string $b): string
    {
        return Money::compare($a, $b) <= 0 ? $a : $b;
    }
}
