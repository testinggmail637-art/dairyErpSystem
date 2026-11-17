<?php

namespace App\Services;

use App\Enums\AnimalSaleStatus;
use App\Enums\BillStatus;
use App\Enums\CycleStatus;
use App\Enums\FarmerPaymentStatus;
use App\Enums\PaymentDirection;
use App\Models\AnimalPayment;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\CustomerPayment;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\Party;
use App\Reports\ReportTable;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Receivables & payables as of a date (spec 7.6 #5): customer receivables aging, farmer payables, party balances.
 * Each balance uses the same formula as its ledger (customer ledger, FarmerPaymentService::balance,
 * PartyLedgerService::balances), counting only entries dated on or before the as-of date. All sums are SQL.
 */
class BalanceReportService
{
    public const REPORTS = ['receivables', 'farmers', 'parties'];

    /** Report => permission needed besides reports.view. */
    public const PERMISSIONS = ['receivables' => 'customers.view', 'farmers' => 'farmer_payments.view', 'parties' => 'animal_trading.view'];

    public const BUCKETS = [30, 60, 90];

    /**
     * Customer balance (opening + finalized bills + adjustments − payments) split by age. Payments clear the oldest
     * debits first (FIFO), so what is still owed is the newest debits (finalized bills and extra-charge adjustments):
     * a running total from the newest debit backwards (SQL window) gives each debit's unpaid part, aged by its date.
     * Whatever is left beyond the debits is the opening balance and goes to the oldest column.
     */
    public function receivablesAging(string $asOf): ReportTable
    {
        $sumBy = fn ($query, string $column, string $dateColumn) => $query->toBase()->where($dateColumn, '<=', $asOf)
            ->groupBy('customer_id')->selectRaw("customer_id, SUM({$column}) AS total");

        $balances = Customer::query()->toBase()
            ->leftJoinSub($sumBy(Bill::query()->where('status', BillStatus::Finalized->value), 'total', 'bill_date'), 'b', 'b.customer_id', '=', 'customers.id')
            ->leftJoinSub($sumBy(CustomerAdjustment::query(), 'amount', 'adjustment_date'), 'a', 'a.customer_id', '=', 'customers.id')
            ->leftJoinSub($sumBy(CustomerPayment::query(), 'amount', 'payment_date'), 'p', 'p.customer_id', '=', 'customers.id')
            ->select(['customers.id', 'customers.code', 'customers.name'])
            ->selectRaw('customers.opening_balance + COALESCE(b.total, 0) + COALESCE(a.total, 0) - COALESCE(p.total, 0) AS balance');

        $debits = Bill::query()->toBase()->where('status', BillStatus::Finalized->value)->where('bill_date', '<=', $asOf)
            ->selectRaw('customer_id, bill_date AS d, 1 AS kind, id, total AS amt')
            ->unionAll(CustomerAdjustment::query()->toBase()->where('amount', '>', 0)->where('adjustment_date', '<=', $asOf)
                ->selectRaw('customer_id, adjustment_date AS d, 0 AS kind, id, amount AS amt'));
        $running = DB::query()->fromSub($debits, 'u')
            ->selectRaw('customer_id, d, amt, SUM(amt) OVER (PARTITION BY customer_id ORDER BY d DESC, kind DESC, id DESC ROWS UNBOUNDED PRECEDING) AS cum');

        [$b1, $b2, $b3] = self::BUCKETS;
        $part = 'GREATEST(0, LEAST(r.amt, bal.balance - (r.cum - r.amt)))';
        $age = 'DATEDIFF(?, r.d)';
        $aged = DB::query()->fromSub($running, 'r')
            ->joinSub($balances, 'bal', 'bal.id', '=', 'r.customer_id')
            ->where('bal.balance', '>', 0)
            ->groupBy('r.customer_id')
            ->select('r.customer_id')
            ->selectRaw("SUM(CASE WHEN {$age} <= {$b1} THEN {$part} ELSE 0 END) AS d1", [$asOf])
            ->selectRaw("SUM(CASE WHEN {$age} > {$b1} AND {$age} <= {$b2} THEN {$part} ELSE 0 END) AS d2", [$asOf, $asOf])
            ->selectRaw("SUM(CASE WHEN {$age} > {$b2} AND {$age} <= {$b3} THEN {$part} ELSE 0 END) AS d3", [$asOf, $asOf])
            ->selectRaw("SUM(CASE WHEN {$age} > {$b3} THEN {$part} ELSE 0 END) AS d4", [$asOf]);

        $lines = DB::query()->fromSub($balances, 'bal')
            ->leftJoinSub($aged, 'g', 'g.customer_id', '=', 'bal.id')
            ->where('bal.balance', '!=', 0)
            ->select(['bal.code', 'bal.name', 'bal.balance', 'g.d1', 'g.d2', 'g.d3', 'g.d4'])
            ->orderByDesc('bal.balance')->orderBy('bal.code')
            ->get();

        $rows = $lines->map(function ($l) {
            $balance = Money::round2((string) $l->balance);
            if (Money::isNegative($balance)) {
                return [$this->name($l), $balance, '0.00', '0.00', '0.00', '0.00'];
            }
            $buckets = [Money::round2((string) ($l->d1 ?? 0)), Money::round2((string) ($l->d2 ?? 0)), Money::round2((string) ($l->d3 ?? 0))];
            $buckets[] = Money::sub($balance, Money::sum($buckets)); // 90+ incl. opening balance

            return [$this->name($l), $balance, ...$buckets];
        });

        return new ReportTable(
            __('Receivables aging'),
            __('As of :date', ['date' => fmt_date($asOf)]),
            [
                ReportTable::column(__('Customer')),
                ReportTable::column(__('Balance'), ReportTable::MONEY),
                ReportTable::column(__('0–30 days'), ReportTable::MONEY),
                ReportTable::column(__('31–60 days'), ReportTable::MONEY),
                ReportTable::column(__('61–90 days'), ReportTable::MONEY),
                ReportTable::column(__('90+ days'), ReportTable::MONEY),
            ],
            $rows->all(),
            $rows->isEmpty() ? null : [__('Total (:count)', ['count' => $rows->count()]), ...array_map(fn ($i) => Money::sum($rows->pluck($i)), range(1, 5))],
            [
                __('Balance = opening + finalized bills + adjustments − payments up to the date (same as the customer ledger). Delivered milk not yet billed is not included.'),
                __('Payments clear the oldest bills first; the unpaid part of each bill is aged by its bill date. The opening balance counts as 90+ days. A negative balance is an advance.'),
            ],
            emptyText: __('No customer has a balance on this date.'),
        );
    }

    /** What we owe each farmer: opening + finalized statements (cycle ended by the date) − advances − statements paid by the date. */
    public function farmerPayables(string $asOf): ReportTable
    {
        $statements = FarmerPayment::query()->toBase()
            ->join('farmer_payment_cycles as c', 'c.id', '=', 'farmer_payments.farmer_payment_cycle_id')
            ->where('c.status', CycleStatus::Finalized->value)->where('c.to_date', '<=', $asOf)
            ->groupBy('farmer_payments.farmer_id')
            ->selectRaw('farmer_payments.farmer_id, SUM(farmer_payments.gross_amount + farmer_payments.bonus - farmer_payments.other_deduction) AS earned')
            ->selectRaw('SUM(CASE WHEN farmer_payments.status = ? AND farmer_payments.paid_on <= ? THEN farmer_payments.net_payable ELSE 0 END) AS paid', [FarmerPaymentStatus::Paid->value, $asOf]);
        $advances = FarmerAdvance::query()->toBase()->where('advance_date', '<=', $asOf)
            ->groupBy('farmer_id')->selectRaw('farmer_id, SUM(amount) AS total');

        $lines = Farmer::query()->toBase()
            ->leftJoinSub($statements, 's', 's.farmer_id', '=', 'farmers.id')
            ->leftJoinSub($advances, 'a', 'a.farmer_id', '=', 'farmers.id')
            ->select(['farmers.code', 'farmers.name', 'farmers.opening_balance'])
            ->selectRaw('COALESCE(s.earned, 0) AS earned, COALESCE(a.total, 0) AS advances, COALESCE(s.paid, 0) AS paid')
            ->selectRaw('farmers.opening_balance + COALESCE(s.earned, 0) - COALESCE(a.total, 0) - COALESCE(s.paid, 0) AS balance')
            ->where(fn ($q) => $q->where('farmers.opening_balance', '!=', 0)->orWhereNotNull('s.farmer_id')->orWhereNotNull('a.farmer_id'))
            ->orderByDesc('balance')->orderBy('farmers.code')
            ->get();

        $rows = $lines->map(fn ($l) => [
            $this->name($l), Money::round2((string) $l->opening_balance), Money::round2((string) $l->earned),
            Money::round2((string) $l->advances), Money::round2((string) $l->paid), Money::round2((string) $l->balance),
        ]);

        return new ReportTable(
            __('Farmer payables'),
            __('As of :date', ['date' => fmt_date($asOf)]),
            [
                ReportTable::column(__('Farmer')),
                ReportTable::column(__('Opening'), ReportTable::MONEY),
                ReportTable::column(__('Milk (finalized)'), ReportTable::MONEY),
                ReportTable::column(__('Advances'), ReportTable::MONEY),
                ReportTable::column(__('Paid'), ReportTable::MONEY),
                ReportTable::column(__('We owe'), ReportTable::MONEY),
            ],
            $rows->all(),
            $rows->isEmpty() ? null : [__('Total (:count)', ['count' => $rows->count()]), ...array_map(fn ($i) => Money::sum($rows->pluck($i)), range(1, 5))],
            [
                __('Milk = finalized payment statements (gross + bonus − other deductions) of cycles ended by the date; milk not yet in a finalized cycle is not included. A negative amount means the farmer owes us (e.g. an advance not yet recovered).'),
            ],
            emptyText: __('No farmer balances.'),
        );
    }

    /** Animal trading parties: opening + sales − purchases − received + paid, split into "they owe us" / "we owe them". */
    public function partyBalances(string $asOf): ReportTable
    {
        $sales = AnimalSale::query()->toBase()->where('status', AnimalSaleStatus::Completed->value)->where('sale_date', '<=', $asOf)
            ->groupBy('party_id')->selectRaw('party_id, SUM(net_amount) AS total');
        $purchases = AnimalPurchase::query()->toBase()->where('purchase_date', '<=', $asOf)
            ->groupBy('party_id')->selectRaw('party_id, SUM(total_amount) AS total');
        $payments = AnimalPayment::query()->toBase()->where('payment_date', '<=', $asOf)->groupBy('party_id')
            ->selectRaw('party_id')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS received', [PaymentDirection::Received->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS paid', [PaymentDirection::Paid->value]);

        $lines = Party::query()->toBase()
            ->leftJoinSub($sales, 's', 's.party_id', '=', 'parties.id')
            ->leftJoinSub($purchases, 'b', 'b.party_id', '=', 'parties.id')
            ->leftJoinSub($payments, 'p', 'p.party_id', '=', 'parties.id')
            ->select(['parties.name', 'parties.opening_balance'])
            ->selectRaw('COALESCE(s.total, 0) AS sold, COALESCE(b.total, 0) AS bought, COALESCE(p.received, 0) AS received, COALESCE(p.paid, 0) AS paid')
            ->selectRaw('parties.opening_balance + COALESCE(s.total, 0) - COALESCE(b.total, 0) - COALESCE(p.received, 0) + COALESCE(p.paid, 0) AS balance')
            ->orderByDesc('balance')->orderBy('parties.name')
            ->get()
            ->reject(fn ($l) => Money::isZero((string) $l->balance) && Money::isZero((string) $l->opening_balance)
                && Money::isZero((string) $l->sold) && Money::isZero((string) $l->bought));

        $rows = $lines->map(function ($l) {
            $balance = Money::round2((string) $l->balance);

            return [
                $l->name, Money::round2((string) $l->opening_balance), Money::round2((string) $l->sold), Money::round2((string) $l->bought),
                Money::round2((string) $l->received), Money::round2((string) $l->paid),
                Money::isNegative($balance) ? '0.00' : $balance, Money::isNegative($balance) ? Money::sub('0', $balance) : '0.00',
            ];
        })->values();

        return new ReportTable(
            __('Party balances'),
            __('As of :date', ['date' => fmt_date($asOf)]),
            [
                ReportTable::column(__('Party')),
                ReportTable::column(__('Opening'), ReportTable::MONEY),
                ReportTable::column(__('Sold to them'), ReportTable::MONEY),
                ReportTable::column(__('Bought from them'), ReportTable::MONEY),
                ReportTable::column(__('Received'), ReportTable::MONEY),
                ReportTable::column(__('Paid'), ReportTable::MONEY),
                ReportTable::column(__('They owe us'), ReportTable::MONEY),
                ReportTable::column(__('We owe them'), ReportTable::MONEY),
            ],
            $rows->all(),
            $rows->isEmpty() ? null : [__('Total (:count)', ['count' => $rows->count()]), ...array_map(fn ($i) => Money::sum($rows->pluck($i)), range(1, 7))],
            [__('Same balance as the party ledger: opening + sales (net of selling costs) − purchases − money received + money paid. Cancelled sales are left out.')],
            emptyText: __('No party balances.'),
        );
    }

    private function name(object $line): string
    {
        return "{$line->code} · {$line->name}";
    }
}
