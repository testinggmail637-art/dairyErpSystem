<?php

namespace App\Services;

use App\Enums\LoanFamily;
use App\Enums\LoanStatus;
use App\Enums\LoanTxnType;
use App\Enums\RepaymentMethod;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanRevision;
use App\Models\LoanTransaction;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changes to an instalment loan's future (spec 7.4A): prepayment (reduce EMI / reduce period), interest rate change
 * (change EMI / keep EMI, change period), close / re-open. Only unpaid future instalments are re-made; paid ones and
 * past transactions never change. Every re-plan writes a loan_revision holding the instalments it replaced, so
 * deleting the prepayment that caused it puts the old schedule back.
 */
class LoanRescheduleService
{
    public const REDUCE_EMI = 'reduce_emi';

    public const REDUCE_TENURE = 'reduce_tenure';

    public function __construct(
        private readonly LoanPaymentService $payments,
        private readonly LoanScheduleCalculator $calculator,
    ) {}

    /** @param  array{txn_date: string, amount: string, mode: string, payment_mode_id: int|string, reference_no?: ?string, note?: ?string, client_uuid?: ?string}  $data */
    public function prepay(Loan $loan, array $data): LoanTransaction
    {
        return DB::transaction(function () use ($loan, $data) {
            $this->lockOpen($loan);
            $this->assertReplannable($loan, $data['mode']);

            $state = $this->payments->state($loan);
            $from = $this->firstOpenRow($state['rows'], $loan);
            $amount = Money::round2($data['amount']);

            if (Money::compare($amount, $state['outstanding']) > 0) {
                throw new DomainException(__('Prepayment is more than the principal still owed (:p).', ['p' => inr($state['outstanding'])]));
            }

            $txn = LoanTransaction::create([
                'loan_id' => $loan->id, 'txn_date' => $data['txn_date'], 'type' => LoanTxnType::Prepayment,
                'amount' => $amount, 'principal_part' => $amount, 'interest_part' => '0.00',
                'payment_mode_id' => $data['payment_mode_id'], 'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null, 'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            $this->replan($loan, $from, Money::sub($state['outstanding'], $amount), $loan->interest_rate, $data['mode'], 'prepayment',
                $data['txn_date'], __('Prepayment of :amount — :how', ['amount' => inr($amount), 'how' => $this->modeLabel($data['mode'])]), $txn->id);

            return $txn;
        });
    }

    /** @param  array{effective_date: string, new_rate: string, mode: string, reason: string}  $data */
    public function changeRate(Loan $loan, array $data): LoanRevision
    {
        return DB::transaction(function () use ($loan, $data) {
            $this->lockOpen($loan);
            $this->assertReplannable($loan, $data['mode']);

            $rows = $this->payments->state($loan)['rows'];
            $from = $rows->first(fn ($r) => $r->installment_no > 0 && $r->due_date->toDateString() >= $data['effective_date']);

            if ($from === null || $from->status === 'paid' || ! Money::isZero(Money::add($from->paid_interest, $from->paid_principal))) {
                throw new DomainException(__('There is no unpaid instalment from this date to re-plan. Pick a later date.'));
            }

            return $this->replan($loan, $from, $from->opening_principal, Money::round2($data['new_rate']), $data['mode'], 'rate_change',
                $data['effective_date'], $data['reason'], null);
        });
    }

    /** Deleting the latest prepayment puts back the schedule it replaced. Call inside the delete transaction. */
    public function undoPrepayment(LoanTransaction $txn): void
    {
        $revision = LoanRevision::query()->where('loan_transaction_id', $txn->id)->first();
        if ($revision === null) {
            return;
        }
        if (LoanRevision::query()->where('loan_id', $txn->loan_id)->where('id', '>', $revision->id)->exists()) {
            throw new DomainException(__('The interest rate was changed after this prepayment, so it cannot be deleted.'));
        }

        $loan = $txn->loan;
        $loan->installments()->whereIn('installment_no', $this->replacedNumbers($revision, $loan))->delete();
        LoanInstallment::insert(array_map(fn (array $row) => $row + ['farm_id' => $loan->farm_id, 'loan_id' => $loan->id,
            'created_at' => now(), 'updated_at' => now()], $revision->schedule_before));
        $loan->update(['emi_amount' => $revision->old_emi, 'tenure_months' => $this->tenureAfter($loan)]);
        $revision->delete();
    }

    /** Close when nothing is owed: principal 0 (instalment loans) or nothing used (revolving / card). */
    public function close(Loan $loan, string $closedOn): void
    {
        DB::transaction(function () use ($loan, $closedOn) {
            $this->lockOpen($loan);

            $owed = $loan->hasSchedule() ? $this->payments->state($loan)['outstanding'] : $this->utilized($loan);
            if (Money::compare($owed, '0') > 0) {
                throw new DomainException(__('This loan still has :owed to pay, so it cannot be closed.', ['owed' => inr($owed)]));
            }

            $loan->update(['status' => LoanStatus::Closed, 'closed_on' => $closedOn]);
        });
    }

    public function reopen(Loan $loan): void
    {
        if ($loan->status !== LoanStatus::Closed) {
            throw new DomainException(__('This loan is not closed.'));
        }

        $loan->update(['status' => LoanStatus::Active, 'closed_on' => null]);
    }

    /** Revolving / card: used = draws + interest + charges (+ card purchases) − repayments − subvention (spec §6). */
    public function utilized(Loan $loan): string
    {
        return app(RevolvingAccountService::class)->utilized($loan);
    }

    /**
     * Replace the instalments from $from on with a new plan for $principal at $rate, keeping their numbers going.
     */
    private function replan(Loan $loan, LoanInstallment $from, string $principal, string $rate, string $mode, string $kind, string $effectiveDate, string $reason, ?int $txnId): LoanRevision
    {
        $old = $loan->installments()->where('installment_no', '>=', $from->installment_no)->orderBy('installment_no')->get();
        $remaining = $old->count();
        $method = $loan->repayment_method;
        $firstDue = $from->due_date;

        $rows = match (true) {
            Money::isZero($principal) => [],
            $mode === self::REDUCE_TENURE => $this->calculator->reducingWithEmi($principal, $rate, $loan->emi_amount, $firstDue, $loan->installment_frequency)
                ?? throw new DomainException(__('The EMI of :emi would not even cover the interest at this rate. Choose "change EMI".', ['emi' => inr($loan->emi_amount)])),
            default => $this->calculator->schedule($method, $principal, $rate, $remaining * $loan->installment_frequency->months(), $firstDue, $loan->installment_frequency)['rows'],
        };
        $newEmi = $rows === [] ? $loan->emi_amount : ($mode === self::REDUCE_TENURE ? $loan->emi_amount : $rows[0]['emi_amount']);
        if ($mode !== self::REDUCE_TENURE && $method === RepaymentMethod::InterestOnly && $rows !== []) {
            $newEmi = $rows[0]['interest_part'];
        }

        $loan->installments()->where('installment_no', '>=', $from->installment_no)->delete();
        $offset = $from->installment_no - 1;
        LoanInstallment::insert(array_map(fn (array $row) => ['installment_no' => $row['installment_no'] + $offset] + $row + [
            'farm_id' => $loan->farm_id, 'loan_id' => $loan->id, 'created_at' => now(), 'updated_at' => now(),
        ], $rows));

        $revision = LoanRevision::create([
            'loan_id' => $loan->id, 'loan_transaction_id' => $txnId, 'kind' => $kind, 'effective_date' => $effectiveDate,
            'old_rate' => $loan->interest_rate, 'new_rate' => $rate, 'old_emi' => $loan->emi_amount, 'new_emi' => $newEmi,
            'reason' => $reason,
            'schedule_before' => $old->map(fn (LoanInstallment $i) => $i->only(['installment_no', 'opening_principal', 'emi_amount',
                'principal_part', 'interest_part', 'closing_principal']) + ['due_date' => $i->due_date->toDateString()])->all(),
        ]);

        $loan->update(['interest_rate' => $rate, 'emi_amount' => $newEmi, 'tenure_months' => $this->tenureAfter($loan)]);

        return $revision;
    }

    /** Months from the start to the last instalment after a re-plan. */
    private function tenureAfter(Loan $loan): int
    {
        $last = $loan->installments()->max('due_date');

        return $last ? max(1, (int) round($loan->start_date->diffInMonths(Carbon::parse($last)))) : (int) $loan->tenure_months;
    }

    /** Instalment numbers that a revision's re-plan created (to delete when undoing it). */
    private function replacedNumbers(LoanRevision $revision, Loan $loan): array
    {
        $first = min(array_column($revision->schedule_before, 'installment_no'));

        return $loan->installments()->where('installment_no', '>=', $first)->pluck('installment_no')->all();
    }

    /** @param  Collection<int, LoanInstallment>  $rows */
    private function firstOpenRow(Collection $rows, Loan $loan): LoanInstallment
    {
        $from = $rows->first(fn ($r) => $r->status !== 'paid');

        if ($from === null) {
            throw new DomainException(__('All instalments are paid — close the loan instead.'));
        }
        if (! Money::isZero(Money::add($from->paid_interest, $from->paid_principal)) || $from->installment_no === 0) {
            throw new DomainException(__('Pay the rest of the current instalment (:amount) first, then the prepayment.', ['amount' => inr($from->balance_due)]));
        }
        if ($loan->moratorium_months > 0 && Money::isZero($from->principal_part) && $loan->repayment_method !== RepaymentMethod::InterestOnly) {
            throw new DomainException(__('Prepayment starts after the moratorium; until then only interest is due.'));
        }

        return $from;
    }

    private function assertReplannable(Loan $loan, string $mode): void
    {
        if ($loan->family() !== LoanFamily::Instalment || $loan->repayment_method === RepaymentMethod::Bullet) {
            throw new DomainException(__('Only EMI and interest-only loans can be re-planned.'));
        }
        if ($mode === self::REDUCE_TENURE && $loan->repayment_method !== RepaymentMethod::EmiReducing) {
            throw new DomainException(__('"Keep EMI, shorter period" is for reducing-balance EMI loans; choose "reduce EMI".'));
        }
    }

    private function lockOpen(Loan $loan): void
    {
        Loan::query()->whereKey($loan->id)->lockForUpdate()->value('id');

        if ($loan->status === LoanStatus::Closed) {
            throw new DomainException(__('This loan is closed. Re-open it first.'));
        }
    }

    private function modeLabel(string $mode): string
    {
        return $mode === self::REDUCE_TENURE ? __('same EMI, shorter period') : __('lower EMI, same period');
    }
}
