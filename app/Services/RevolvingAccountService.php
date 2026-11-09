<?php

namespace App\Services;

use App\Enums\InstallmentFrequency;
use App\Enums\LoanStatus;
use App\Enums\LoanTxnType;
use App\Models\Loan;
use App\Models\LoanTransaction;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Overdraft / cash credit / KCC accounts (spec 7.4A). Used = withdrawals + interest debited + charges − repayments −
 * subvention credits (+ card purchases for credit cards). A withdrawal may not take "used" above the limit; a repayment
 * or credit may not be more than what is used. Interest debited → Finance → Loan Interest expense, charges → Bank
 * Charges; withdrawals, repayments and subvention are not expenses. The bank's interest is entered from the statement;
 * estimate() shows what the daily balance gives (balance × rate × days ÷ 36500) to check it.
 */
class RevolvingAccountService
{
    public const TYPES = [LoanTxnType::OdWithdrawal, LoanTxnType::OdRepayment, LoanTxnType::OdInterest, LoanTxnType::Charge, LoanTxnType::SubventionCredit];

    public function __construct(private readonly FinanceExpenseService $expenses) {}

    /** "+ amount" for what raises the balance used, "− amount" for what lowers it. */
    public static function signedSql(string $column = 'amount'): string
    {
        return sprintf("CASE WHEN type IN ('%s','%s','%s') THEN %s WHEN type IN ('%s','%s') THEN -%s ELSE 0 END",
            LoanTxnType::OdWithdrawal->value, LoanTxnType::OdInterest->value, LoanTxnType::Charge->value, $column,
            LoanTxnType::OdRepayment->value, LoanTxnType::SubventionCredit->value, $column);
    }

    /** Used balance, optionally up to and including a date and without one entry (when it is being edited). */
    public function utilized(Loan $loan, ?string $upTo = null, ?int $exceptId = null): string
    {
        $used = (string) $loan->transactions()
            ->when($upTo, fn ($q) => $q->where('txn_date', '<=', $upTo))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->sum(DB::raw(self::signedSql()));

        if ($loan->isCard()) {
            $used = Money::add($used, (string) DB::table('expenses')->where('paid_by_card_id', $loan->id)->whereNull('deleted_at')
                ->when($upTo, fn ($q) => $q->where('expense_date', '<=', $upTo))->sum('amount'));
        }

        return Money::round2($used);
    }

    /**
     * Used per account for a list page — one grouped query (+ one for card purchases).
     *
     * @param  Collection<int, Loan>  $loans
     * @return array<int, string> loan id => used
     */
    public function usedFor(Collection $loans): array
    {
        $ids = $loans->reject->hasSchedule()->modelKeys();
        if ($ids === []) {
            return [];
        }

        $used = LoanTransaction::query()->whereIn('loan_id', $ids)->groupBy('loan_id')
            ->selectRaw('loan_id, SUM('.self::signedSql().') AS used')->pluck('used', 'loan_id');
        $cards = DB::table('expenses')->whereIn('paid_by_card_id', $ids)->whereNull('deleted_at')
            ->groupBy('paid_by_card_id')->selectRaw('paid_by_card_id, SUM(amount) AS total')->pluck('total', 'paid_by_card_id');

        return collect($ids)->mapWithKeys(fn ($id) => [$id => Money::add((string) ($used[$id] ?? '0'), (string) ($cards[$id] ?? '0'))])->all();
    }

    /** Entries newest first with the running balance after each (window function over date, id). */
    public function ledger(Loan $loan, int $perPage = 50): LengthAwarePaginator
    {
        return DB::query()->fromSub(
            LoanTransaction::query()->where('loan_id', $loan->id)->toBase()
                ->leftJoin('payment_modes as m', 'm.id', '=', 'loan_transactions.payment_mode_id')
                ->select(['loan_transactions.id', 'txn_date', 'type', 'amount', 'reference_no', 'note', 'm.name as mode'])
                ->selectRaw('SUM('.self::signedSql('amount').') OVER (ORDER BY txn_date, loan_transactions.id ROWS UNBOUNDED PRECEDING) AS balance'),
            'l')
            ->orderByDesc('txn_date')->orderByDesc('id')
            ->paginate($perPage, ['*'], 'txn_page');
    }

    /**
     * Interest the daily used balance gives for a period: Σ (closing balance of the day × rate ÷ 36500).
     *
     * @return array{estimate: string, bank: string, days: int, average: string}
     */
    public function estimate(Loan $loan, string $from, string $to): array
    {
        $balance = $this->utilized($loan, Carbon::parse($from)->subDay()->toDateString());
        $deltas = $loan->transactions()->whereBetween('txn_date', [$from, $to])
            ->groupBy('txn_date')->selectRaw('txn_date, SUM('.self::signedSql().') AS delta')
            ->pluck('delta', 'txn_date')->mapWithKeys(fn ($d, $date) => [Carbon::parse($date)->toDateString() => (string) $d]);

        $sum = '0';
        $days = 0;
        for ($day = Carbon::parse($from); $day->lte(Carbon::parse($to)); $day->addDay()) {
            $balance = Money::add($balance, $deltas[$day->toDateString()] ?? '0');
            $sum = bcadd($sum, Money::compare($balance, '0') > 0 ? $balance : '0', 2);
            $days++;
        }

        return [
            'estimate' => Money::round2(bcdiv(bcmul($sum, $loan->interest_rate, 6), '36500', 6)),
            'bank' => Money::round2((string) $loan->transactions()->where('type', LoanTxnType::OdInterest)->whereBetween('txn_date', [$from, $to])->sum('amount')),
            'days' => $days,
            'average' => $days ? Money::round2(bcdiv($sum, (string) $days, 6)) : '0.00',
        ];
    }

    /**
     * The last finished interest debit period (calendar months / quarters / halves / years) — the estimate's default.
     *
     * @return array{0: string, 1: string}
     */
    public function lastCycle(Loan $loan): array
    {
        $months = ($loan->interest_debit_cycle ?? InstallmentFrequency::Monthly)->months();
        $currentStart = today()->startOfYear()->addMonths(intdiv(today()->month - 1, $months) * $months);
        $from = $currentStart->copy()->subMonths($months);

        return [$from->toDateString(), $currentStart->copy()->subDay()->toDateString()];
    }

    /**
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
            if (! app(LoanPaymentService::class)->isLatest($txn)) {
                throw new DomainException(__('Only the latest entry of an account can be changed or deleted. Change the newer ones first.'));
            }
            $this->apply($txn->loan, $txn, $data);

            return $txn;
        });
    }

    /** @param  array<string, mixed>  $data */
    private function apply(Loan $loan, LoanTransaction $txn, array $data): void
    {
        Loan::query()->whereKey($loan->id)->lockForUpdate()->value('id');

        if ($loan->status === LoanStatus::Closed) {
            throw new DomainException(__('This account is closed. Re-open it to add or change entries.'));
        }
        if (! $loan->isRevolving()) {
            throw new DomainException(__('Withdrawals and repayments are for overdraft, cash credit and KCC accounts.'));
        }

        $type = LoanTxnType::from($data['type']);
        $amount = Money::round2($data['amount']);
        $used = $this->utilized($loan, null, $txn->exists ? $txn->id : null);

        if ($type === LoanTxnType::OdWithdrawal && Money::compare(Money::add($used, $amount), $loan->principal_amount) > 0) {
            throw new DomainException(__('This withdrawal goes over the limit: only :available is available.', ['available' => inr(Money::sub($loan->principal_amount, $used))]));
        }
        if (in_array($type, [LoanTxnType::OdRepayment, LoanTxnType::SubventionCredit], true) && Money::compare($amount, $used) > 0) {
            throw new DomainException(__('This is more than the :used now used.', ['used' => inr($used)]));
        }

        $modeId = match ($type) {
            LoanTxnType::OdInterest, LoanTxnType::Charge => $data['payment_mode_id'] ?? $this->expenses->bankMode(),
            LoanTxnType::SubventionCredit => null,
            default => $data['payment_mode_id'] ?? null,
        };

        $txn->fill([
            'txn_date' => $data['txn_date'],
            'type' => $type,
            'amount' => $amount,
            'interest_part' => $type === LoanTxnType::OdInterest ? $amount : '0.00',
            'principal_part' => '0.00',
            'payment_mode_id' => $modeId,
            'reference_no' => $data['reference_no'] ?? null,
            'note' => $data['note'] ?? null,
        ])->save();

        $costs = in_array($type, [LoanTxnType::OdInterest, LoanTxnType::Charge], true);
        $expense = $this->expenses->sync($txn, $costs ? $amount : null, $data['txn_date'],
            $type === LoanTxnType::Charge ? FinanceExpenseService::CHARGES : FinanceExpenseService::INTEREST, $modeId ? (int) $modeId : null,
            $loan->lender_name, $type->label().' · '.$loan->loan_no.(filled($data['note'] ?? null) ? ' · '.$data['note'] : ''), $loan->id);

        if ($txn->expense_id !== $expense?->id) {
            $txn->update(['expense_id' => $expense?->id]);
        }
    }
}
