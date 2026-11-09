<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\LoanTxnType;
use App\Enums\LoanType;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanTransaction;
use App\Reports\ReportTable;
use App\Support\Format;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Loan reports (spec 7.4A): summary as on a date, instalments due, interest & principal per loan / type, loan statement,
 * monthly payments by type. Totals are grouped SQL on (farm_id, loan_id, txn_date); only the statement and the due list
 * walk one loan's rows. Interest = interest part of payments + interest debited; principal = principal part of
 * payments and prepayments (OD / card repayments are not "principal" — they only lower the used balance).
 */
class LoanReportService
{
    public const REPORTS = ['summary', 'due', 'interest', 'statement', 'monthly'];

    public function __construct(
        private readonly LoanPaymentService $payments,
        private readonly RevolvingAccountService $accounts,
    ) {}

    public function summary(string $date): ReportTable
    {
        $loans = Loan::query()->where('start_date', '<=', $date)->orderBy('loan_type')->orderBy('lender_name')->get();
        $ids = $loans->modelKeys();
        $txn = $this->totalsByLoan(LoanTransaction::query()->whereIn('loan_id', $ids)->where('txn_date', '<=', $date))
            ->selectRaw('SUM('.RevolvingAccountService::signedSql().') AS used')->get()->keyBy('loan_id');
        $schedule = LoanInstallment::query()->whereIn('loan_id', $ids)->groupBy('loan_id')
            ->selectRaw('loan_id, SUM(principal_part) AS principal')->pluck('principal', 'loan_id');
        $purchases = DB::table('expenses')->whereIn('paid_by_card_id', $ids)->whereNull('deleted_at')->where('expense_date', '<=', $date)
            ->groupBy('paid_by_card_id')->selectRaw('paid_by_card_id, SUM(amount) AS total')->pluck('total', 'paid_by_card_id');

        $rows = $loans->map(function (Loan $l) use ($txn, $schedule, $purchases) {
            $t = $txn[$l->id] ?? null;
            $owed = $l->hasSchedule()
                ? Money::sub((string) ($schedule[$l->id] ?? $l->principal_amount), (string) ($t->principal ?? '0'))
                : Money::add((string) ($t->used ?? '0'), (string) ($purchases[$l->id] ?? '0'));

            return [$l->loan_no, $l->loan_type->label(), $l->lender_name, $l->principal_amount, $l->interest_rate.' %',
                $l->hasSchedule() ? $l->emi_amount : null, Format::date($l->start_date), Money::round2((string) ($t->interest ?? '0')),
                Money::round2((string) ($t->principal ?? '0')), Money::round2((string) ($t->charges ?? '0')), Money::round2($owed), $l->status->label()];
        });

        return new ReportTable(
            __('Loan summary'),
            __('As on :date', ['date' => Format::date($date)]),
            [
                ReportTable::column(__('Loan no.')), ReportTable::column(__('Type')), ReportTable::column(__('Lender')),
                ReportTable::column(__('Amount / limit'), ReportTable::MONEY), ReportTable::column(__('Rate')),
                ReportTable::column(__('EMI'), ReportTable::MONEY), ReportTable::column(__('Start')),
                ReportTable::column(__('Interest paid'), ReportTable::MONEY), ReportTable::column(__('Principal paid'), ReportTable::MONEY),
                ReportTable::column(__('Charges'), ReportTable::MONEY), ReportTable::column(__('Outstanding / used'), ReportTable::MONEY),
                ReportTable::column(__('Status')),
            ],
            $rows->all(),
            [__('Total (:count)', ['count' => $rows->count()]), '', '', '', '', '', '', Money::sum($rows->pluck(7)), Money::sum($rows->pluck(8)),
                Money::sum($rows->pluck(9)), Money::sum($rows->pluck(10)), ''],
            [__('Outstanding = principal still to repay on instalment loans; used = balance on OD / CC / KCC and credit cards. Amounts up to and including the date.')],
            emptyText: __('No loans on this date.'),
        );
    }

    public function due(string $from, string $to): ReportTable
    {
        $loanIds = LoanInstallment::query()->whereBetween('due_date', [$from, $to])->distinct()->pluck('loan_id');
        $rows = Loan::query()->whereIn('id', $loanIds)->where('status', LoanStatus::Active)->get()
            ->flatMap(fn (Loan $l) => $this->payments->state($l)['rows']
                ->filter(fn ($r) => $r->due_date->between($from, $to))
                ->map(fn ($r) => [$r->due_date->toDateString(), $l->loan_no, $l->lender_name, $r->installment_no, $r->emi_amount,
                    Money::add($r->paid_interest, $r->paid_principal), $r->balance_due, $r->status]))
            ->sortBy([[0, 'asc'], [1, 'asc']])->values();

        $labels = ['paid' => __('Paid'), 'partially_paid' => __('Part paid'), 'overdue' => __('Overdue'), 'due' => __('Due')];

        return new ReportTable(
            __('Instalments due'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Due date'), ReportTable::DATE), ReportTable::column(__('Loan no.')), ReportTable::column(__('Lender')),
                ReportTable::column(__('No.'), ReportTable::NUMBER), ReportTable::column(__('Instalment'), ReportTable::MONEY),
                ReportTable::column(__('Paid'), ReportTable::MONEY), ReportTable::column(__('Balance'), ReportTable::MONEY), ReportTable::column(__('Status')),
            ],
            $rows->map(fn ($r) => [...array_slice($r, 0, 7), $labels[$r[7]]])->all(),
            [__('Total (:count)', ['count' => $rows->count()]), '', '', '', Money::sum($rows->pluck(4)), Money::sum($rows->pluck(5)), Money::sum($rows->pluck(6)), ''],
            [__('Active instalment loans. Paid = what the payments so far cover of each instalment, oldest first.')],
            emptyText: __('No instalments due in this period.'),
        );
    }

    public function interest(string $from, string $to): ReportTable
    {
        $lines = $this->totalsByLoan(LoanTransaction::query()->whereBetween('txn_date', [$from, $to]))
            ->selectRaw('SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS subvention', [LoanTxnType::SubventionCredit->value])->get()
            ->keyBy('loan_id');
        $loans = Loan::withTrashed()->whereIn('id', $lines->keys())->orderBy('loan_type')->orderBy('lender_name')->get();

        $rows = [];
        $strong = [];
        $line = fn (string $label, string $type, array $v) => [$label, $type, $v['interest'], $v['subvention'], Money::sub($v['interest'], $v['subvention']),
            $v['charges'], $v['principal'], Money::add(Money::add($v['interest'], $v['charges']), Money::sub($v['principal'], $v['subvention']))];
        $grand = ['interest' => '0', 'subvention' => '0', 'charges' => '0', 'principal' => '0'];

        foreach ($loans->groupBy(fn ($l) => $l->loan_type->value) as $type => $group) {
            $sum = ['interest' => '0', 'subvention' => '0', 'charges' => '0', 'principal' => '0'];
            foreach ($group as $l) {
                $v = collect($sum)->map(fn ($x, $k) => Money::round2((string) $lines[$l->id]->{$k}))->all();
                $rows[] = $line($l->lender_name.' · '.$l->loan_no, $l->loan_type->label(), $v);
                $sum = collect($sum)->map(fn ($x, $k) => Money::add($x, $v[$k]))->all();
            }
            if ($group->count() > 1) {
                $strong[] = count($rows);
                $rows[] = $line(__('Total :type', ['type' => LoanType::from($type)->label()]), '', $sum);
            }
            $grand = collect($grand)->map(fn ($x, $k) => Money::add($x, $sum[$k]))->all();
        }

        return new ReportTable(
            __('Interest & principal paid'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Loan')), ReportTable::column(__('Type')), ReportTable::column(__('Interest'), ReportTable::MONEY),
                ReportTable::column(__('Subvention'), ReportTable::MONEY), ReportTable::column(__('Net interest'), ReportTable::MONEY),
                ReportTable::column(__('Charges'), ReportTable::MONEY), ReportTable::column(__('Principal'), ReportTable::MONEY),
                ReportTable::column(__('Total cost + principal'), ReportTable::MONEY),
            ],
            $rows,
            $line(__('Total'), '', $grand),
            [__('For the income-tax file choose 1 April – 31 March. Net interest and charges are business expenses; principal is not.')],
            $strong,
            __('No loan payments in this period.'),
        );
    }

    public function statement(?Loan $loan, string $from, string $to): ReportTable
    {
        $columns = [
            ReportTable::column(__('Date'), ReportTable::DATE), ReportTable::column(__('Entry')), ReportTable::column(__('Details')),
            ReportTable::column(__('Amount'), ReportTable::MONEY), ReportTable::column(__('Interest'), ReportTable::MONEY),
            ReportTable::column(__('Principal'), ReportTable::MONEY), ReportTable::column(__('Balance'), ReportTable::MONEY),
        ];
        if (! $loan) {
            return new ReportTable(__('Loan statement'), $this->period($from, $to), $columns, [], emptyText: __('Choose a loan.'));
        }

        $before = (string) $loan->transactions()->where('txn_date', '<', $from)
            ->sum(DB::raw($loan->hasSchedule() ? 'principal_part' : RevolvingAccountService::signedSql()));
        $opening = $loan->hasSchedule()
            ? Money::sub((string) $loan->installments()->sum('principal_part') ?: $loan->principal_amount, $before)
            : $this->accounts->utilized($loan, date('Y-m-d', strtotime($from.' -1 day')));

        $entries = $loan->transactions()->whereBetween('txn_date', [$from, $to])->orderBy('txn_date')->orderBy('id')
            ->get(['id', 'txn_date', 'type', 'amount', 'interest_part', 'principal_part', 'reference_no', 'note'])
            ->map(fn ($t) => ['date' => $t->txn_date->toDateString(), 'entry' => RevolvingAccountService::label($loan, $t->type),
                'details' => collect([$t->reference_no, $t->note])->filter()->implode(' · '), 'amount' => $t->amount,
                'interest' => $t->interest_part, 'principal' => $t->principal_part,
                'delta' => $loan->hasSchedule() ? Money::sub('0', $t->principal_part) : $this->signed($t->type, $t->amount), 'order' => 't'.$t->id]);
        if ($loan->isCard()) {
            $entries = $entries->concat(DB::table('expenses')->where('paid_by_card_id', $loan->id)->whereNull('deleted_at')
                ->whereBetween('expense_date', [$from, $to])->get(['id', 'expense_date', 'amount', 'paid_to_text', 'note'])
                ->map(fn ($e) => ['date' => $e->expense_date, 'entry' => __('Card purchase (expense)'), 'details' => $e->paid_to_text ?? $e->note,
                    'amount' => $e->amount, 'interest' => null, 'principal' => null, 'delta' => $e->amount, 'order' => 'e'.$e->id]))
                ->sortBy([['date', 'asc'], ['order', 'asc']]);
        }

        $balance = Money::round2($opening);
        $rows = [[$from, __('Opening balance'), '', null, null, null, $balance]];
        foreach ($entries as $e) {
            $balance = Money::add($balance, $e['delta']);
            $rows[] = [$e['date'], $e['entry'], $e['details'], Money::round2($e['amount']), $e['interest'], $e['principal'], $balance];
        }

        return new ReportTable(
            __('Loan statement').' · '.$loan->lender_name.' · '.$loan->loan_no,
            $this->period($from, $to),
            $columns,
            $rows,
            [__('Closing balance'), '', '', Money::sum($entries->pluck('amount')), Money::sum($entries->pluck('interest')->filter()),
                Money::sum($entries->pluck('principal')->filter()), $balance],
            [$loan->hasSchedule() ? __('Balance = principal still to repay.') : __('Balance = amount used (withdrawals, interest, charges and card purchases less repayments).')],
            [0],
        );
    }

    public function monthly(string $from, string $to): ReportTable
    {
        $lines = LoanTransaction::query()->join('loans', 'loans.id', '=', 'loan_transactions.loan_id')
            ->whereBetween('txn_date', [$from, $to])
            ->groupByRaw("DATE_FORMAT(txn_date, '%Y-%m'), loans.loan_type")
            ->selectRaw("DATE_FORMAT(txn_date, '%Y-%m') AS month, loans.loan_type")
            ->selectRaw('SUM(interest_part) AS interest, SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS charges, SUM(principal_part) AS principal',
                [LoanTxnType::Charge->value])
            ->orderBy('month')->orderBy('loans.loan_type')->get()
            ->map(fn ($l) => [$l->month, LoanType::from($l->loan_type)->label(), Money::round2((string) $l->interest), Money::round2((string) $l->charges),
                Money::round2((string) $l->principal), Money::round2(Money::add(Money::add((string) $l->interest, (string) $l->charges), (string) $l->principal))]);

        return new ReportTable(
            __('Monthly loan payments by type'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Month')), ReportTable::column(__('Type')), ReportTable::column(__('Interest'), ReportTable::MONEY),
                ReportTable::column(__('Charges'), ReportTable::MONEY), ReportTable::column(__('Principal'), ReportTable::MONEY),
                ReportTable::column(__('Total'), ReportTable::MONEY),
            ],
            $lines->map(fn ($r) => [Carbon::createFromFormat('!Y-m', $r[0])->translatedFormat('M Y'), ...array_slice($r, 1)])->all(),
            [__('Total'), '', Money::sum($lines->pluck(2)), Money::sum($lines->pluck(3)), Money::sum($lines->pluck(4)), Money::sum($lines->pluck(5))],
            [__('Interest = interest part of payments + interest debited on OD / card. Principal = principal part of EMIs and prepayments.')],
            emptyText: __('No loan payments in this period.'),
        );
    }

    /** Interest, principal and charges per loan (grouped SQL); callers add their own date range and columns. */
    private function totalsByLoan($query)
    {
        return $query->groupBy('loan_id')->select('loan_id')
            ->selectRaw('SUM(interest_part) AS interest, SUM(principal_part) AS principal, SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS charges',
                [LoanTxnType::Charge->value]);
    }

    private function signed(LoanTxnType $type, string $amount): string
    {
        return match ($type) {
            LoanTxnType::OdWithdrawal, LoanTxnType::OdInterest, LoanTxnType::Charge => $amount,
            LoanTxnType::OdRepayment, LoanTxnType::SubventionCredit => Money::sub('0', $amount),
            default => '0',
        };
    }

    private function period(string $from, string $to): string
    {
        return Format::date($from).' – '.Format::date($to);
    }
}
