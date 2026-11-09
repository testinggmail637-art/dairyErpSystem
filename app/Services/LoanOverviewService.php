<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Active loans of the current farm at a glance (spec 7.4A): dashboard loan cards and the daily loans:reminders command.
 * A handful of grouped queries for all loans together (+ two per credit card for its statement).
 */
class LoanOverviewService
{
    public function __construct(
        private readonly LoanPaymentService $payments,
        private readonly RevolvingAccountService $accounts,
    ) {}

    /**
     * @return array{loans: Collection<int, Loan>, outstanding: string, instalment_count: int, used: string, limit: string,
     *     due_soon: list<array{loan: Loan, no: int, due_date: string, due: string}>, due_soon_amount: string,
     *     overdue: list<array{loan: Loan, no: int, due_date: string, due: string}>, overdue_amount: string,
     *     cards: list<array{loan: Loan, bill: array<string, mixed>}>, part_paid: int}
     */
    public function overview(): array
    {
        $loans = Loan::query()->where('status', LoanStatus::Active)->orderBy('lender_name')->get();
        $summaries = $this->payments->summaries($loans);
        $used = $this->accounts->usedFor($loans);
        $week = today()->addDays(7)->toDateString();

        $out = ['loans' => $loans, 'outstanding' => '0.00', 'instalment_count' => 0, 'used' => '0.00', 'limit' => '0.00',
            'due_soon' => [], 'due_soon_amount' => '0.00', 'overdue' => [], 'overdue_amount' => '0.00', 'cards' => [], 'part_paid' => 0];

        foreach ($loans as $loan) {
            if ($loan->hasSchedule()) {
                $s = $summaries[$loan->id] ?? null;
                $out['instalment_count']++;
                $out['outstanding'] = Money::add($out['outstanding'], $s['outstanding'] ?? $loan->principal_amount);
                foreach ($s['open'] ?? [] as $row) {
                    $key = $row['due_date'] < today()->toDateString() ? 'overdue' : ($row['due_date'] <= $week ? 'due_soon' : null);
                    if ($key) {
                        $out[$key][] = ['loan' => $loan] + $row;
                        $out[$key.'_amount'] = Money::add($out[$key.'_amount'], $row['due']);
                    }
                }

                continue;
            }

            $out['used'] = Money::add($out['used'], $used[$loan->id] ?? '0');
            $out['limit'] = Money::add($out['limit'], $loan->principal_amount);
            if ($loan->isCard() && ($bill = $this->accounts->card($loan))) {
                $out['cards'][] = ['loan' => $loan, 'bill' => $bill];
                $out['part_paid'] += $bill['part_paid'] ? 1 : 0;
            }
        }

        return $out;
    }

    /**
     * Reminder lines for today (spec 7.4A): instalments 3 days before and on the due date, overdue ones on the 1st day
     * and then every 7 days, card bills 3 days before the due date, revolving accounts on the last day of an interest
     * debit cycle and 15 days before renewal.
     *
     * @return list<array{loan: Loan, message: string}>
     */
    public function reminders(): array
    {
        $today = today();
        $data = $this->overview();
        $lines = [];
        $name = fn (Loan $l) => $l->lender_name.' ('.$l->loan_no.')';

        foreach ([...$data['due_soon'], ...$data['overdue']] as $row) {
            $due = Carbon::parse($row['due_date']);
            $late = (int) $due->diffInDays($today, false);
            $message = match (true) {
                $late === -3 => ':loan: instalment :no of :amount is due on :date (in 3 days).',
                $late === 0 => ':loan: instalment :no of :amount is due today.',
                $late > 0 && $late % 7 === 1 => ':loan: instalment :no of :amount was due on :date — :days days overdue.',
                default => null,
            };
            if ($message) {
                $lines[] = ['loan' => $row['loan'], 'message' => __($message, ['loan' => $name($row['loan']), 'no' => $row['no'],
                    'amount' => inr($row['due']), 'date' => fmt_date($due), 'days' => $late])];
            }
        }

        foreach ($data['cards'] as ['loan' => $card, 'bill' => $bill]) {
            if ($bill['due_date']->isSameDay($today->copy()->addDays(3)) && Money::compare($bill['to_pay'], '0') > 0) {
                $lines[] = ['loan' => $card, 'message' => __(':loan: card bill of :amount is due on :date (in 3 days). Pay it in full to avoid interest.',
                    ['loan' => $name($card), 'amount' => inr($bill['to_pay']), 'date' => fmt_date($bill['due_date'])])];
            }
        }

        foreach ($data['loans']->filter->isRevolving() as $account) {
            [, $cycleEnd] = $this->accounts->lastCycle($account);
            $months = ($account->interest_debit_cycle?->months() ?? 1);
            if (Carbon::parse($cycleEnd)->addMonthsNoOverflow($months)->endOfMonth()->isSameDay($today)) {
                $estimate = $this->accounts->estimate($account, Carbon::parse($cycleEnd)->addDay()->toDateString(), $today->toDateString());
                $lines[] = ['loan' => $account, 'message' => __(':loan: the bank debits interest today (about :amount by the daily balance). Enter it from the statement.',
                    ['loan' => $name($account), 'amount' => inr($estimate['estimate'])])];
            }
            if ($account->od_renewal_date?->isSameDay($today->copy()->addDays(15))) {
                $lines[] = ['loan' => $account, 'message' => __(':loan: limit renewal is due on :date (in 15 days).',
                    ['loan' => $name($account), 'date' => fmt_date($account->od_renewal_date)])];
            }
        }

        return $lines;
    }
}
