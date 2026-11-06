<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Reports\ReportTable;
use App\Support\Format;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer ledger (spec Section 6): opening balance, finalized bills (+), adjustments (±) and payments (−) in date
 * order with a running balance — the same balance as CustomerBalanceService. "Unbilled" (delivered, not yet billed)
 * is shown apart and is not in the balance.
 *
 * Built like the vendor ledger: sums and the running balance (window function) run on a lean UNION of
 * (date, sort, id, type, debit, credit); bill numbers / modes / reasons are fetched only for the rows shown.
 */
class CustomerLedgerService
{
    public const PER_PAGE = 100;

    public function balanceBefore(Customer $customer, ?string $from): string
    {
        if ($from === null) {
            return Money::round2($customer->opening_balance);
        }

        $row = DB::query()->fromSub($this->entries($customer, null, $from, false), 'e')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        return Money::sub(Money::add($customer->opening_balance, (string) $row->debit), (string) $row->credit);
    }

    /**
     * @param  array<string, mixed>  $query  kept on page links
     * @return array{opening: string, debit: string, credit: string, closing: string, unbilled: string, rows: LengthAwarePaginator}
     */
    public function ledger(Customer $customer, ?string $from, ?string $to, int $page = 1, array $query = []): array
    {
        $opening = $this->balanceBefore($customer, $from);
        $entries = $this->entries($customer, $from, $to, true);

        $totals = DB::query()->fromSub($entries, 'e')
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        $rows = $this->withBalance($entries, $opening)->forPage($page, self::PER_PAGE)->get();

        $debit = Money::round2((string) $totals->debit);
        $credit = Money::round2((string) $totals->credit);

        return [
            'opening' => $opening,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => Money::sub(Money::add($opening, $debit), $credit),
            'unbilled' => $this->unbilled($customer),
            'rows' => new LengthAwarePaginator($this->withDetails($rows), (int) $totals->cnt, self::PER_PAGE, $page, [
                'path' => request()->url(),
                'query' => $query,
            ]),
        ];
    }

    /** The whole period as a ReportTable for Excel / PDF (one customer: a few hundred rows a year at most). */
    public function table(Customer $customer, ?string $from, ?string $to): ReportTable
    {
        $opening = $this->balanceBefore($customer, $from);
        $rows = $this->withDetails($this->withBalance($this->entries($customer, $from, $to, true), $opening)->get());

        $debit = Money::sum($rows->pluck('debit'));
        $credit = Money::sum($rows->pluck('credit'));

        return new ReportTable(
            title: __('Customer ledger · :name (:code)', ['name' => $customer->name, 'code' => $customer->code]),
            period: $from || $to
                ? __(':from to :to', ['from' => $from ? Format::date($from) : __('start'), 'to' => $to ? Format::date($to) : __('today')])
                : __('All entries'),
            columns: [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Entry')),
                ReportTable::column(__('Details')),
                ReportTable::column(__('Charged'), ReportTable::MONEY),
                ReportTable::column(__('Paid / less'), ReportTable::MONEY),
                ReportTable::column(__('Balance'), ReportTable::MONEY),
            ],
            rows: [
                [$from, __('Opening balance'), '', null, null, $opening],
                ...$rows->map(fn ($r) => [$r->entry_date, $this->typeLabel($r->type), $r->detail,
                    Money::isZero($r->debit) ? null : Money::round2($r->debit),
                    Money::isZero($r->credit) ? null : Money::round2($r->credit),
                    Money::round2($r->balance)])->all(),
            ],
            totals: [null, __('Total'), '', $debit, $credit, Money::sub(Money::add($opening, $debit), $credit)],
            notes: [__('Delivered but not yet billed: :amount (not in the balance).', ['amount' => Format::money($this->unbilled($customer))])],
            strongRows: [0],
        );
    }

    public function unbilled(Customer $customer): string
    {
        return Money::round2((string) DB::table('deliveries')->where('customer_id', $customer->id)
            ->where('status', DeliveryStatus::Delivered->value)->whereNull('bill_id')->sum('amount'));
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'bill' => __('Bill'),
            'adjustment' => __('Adjustment'),
            default => __('Payment'),
        };
    }

    private function withBalance(Builder $entries, string $opening): Builder
    {
        return DB::query()->fromSub($entries, 'e')
            ->select('e.*')
            // CAST keeps it exact: a bound string added to a DECIMAL would otherwise become a DOUBLE.
            ->selectRaw('CAST(? AS DECIMAL(14,2)) + SUM(debit - credit) OVER (ORDER BY entry_date, sort_key, id ROWS UNBOUNDED PRECEDING) AS balance', [$opening])
            ->orderBy('entry_date')->orderBy('sort_key')->orderBy('id');
    }

    /**
     * Bills (by bill date), adjustments (+ as debit, − as credit) and payments of one customer as one lean list.
     * Same day: bill, then adjustment, then payment. $inclusiveFrom=false gives everything strictly before $to.
     */
    private function entries(Customer $customer, ?string $from, ?string $to, bool $inclusiveFrom): Builder
    {
        $range = function (Builder $q, string $column) use ($from, $to, $inclusiveFrom) {
            $q->when($from, fn ($q) => $q->where($column, '>=', $from))
                ->when($to, fn ($q) => $inclusiveFrom ? $q->where($column, '<=', $to) : $q->where($column, '<', $to));
        };

        $bills = DB::table('bills')->where('customer_id', $customer->id)->where('status', BillStatus::Finalized->value)
            ->tap(fn ($q) => $range($q, 'bill_date'))
            ->selectRaw("bill_date AS entry_date, 1 AS sort_key, id, 'bill' AS type, total AS debit, 0 AS credit");

        $adjustments = DB::table('customer_adjustments')->where('customer_id', $customer->id)
            ->tap(fn ($q) => $range($q, 'adjustment_date'))
            ->selectRaw("adjustment_date AS entry_date, 2 AS sort_key, id, 'adjustment' AS type, GREATEST(amount, 0) AS debit, GREATEST(-amount, 0) AS credit");

        $payments = DB::table('customer_payments')->where('customer_id', $customer->id)
            ->tap(fn ($q) => $range($q, 'payment_date'))
            ->selectRaw("payment_date AS entry_date, 3 AS sort_key, id, 'payment' AS type, 0 AS debit, amount AS credit");

        return $bills->unionAll($adjustments)->unionAll($payments);
    }

    /**
     * Bill number + period, adjustment reason, payment mode + reference — three small keyed queries for the given rows.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function withDetails(Collection $rows): Collection
    {
        $ids = fn (string $type) => $rows->where('type', $type)->pluck('id')->all();

        $bills = ($b = $ids('bill')) ? DB::table('bills')->whereIn('id', $b)->get(['id', 'bill_no', 'period_from', 'period_to'])->keyBy('id') : collect();
        $adjustments = ($a = $ids('adjustment')) ? DB::table('customer_adjustments')->whereIn('id', $a)->pluck('reason', 'id') : collect();
        $payments = ($p = $ids('payment')) ? DB::table('customer_payments')->leftJoin('payment_modes as m', 'm.id', '=', 'customer_payments.payment_mode_id')
            ->whereIn('customer_payments.id', $p)
            ->selectRaw("customer_payments.id AS id, CONCAT_WS(' · ', m.name, customer_payments.reference_no, customer_payments.note) AS info")
            ->pluck('info', 'id') : collect();

        return $rows->map(function (object $row) use ($bills, $adjustments, $payments) {
            $row->detail = match ($row->type) {
                'bill' => ($bill = $bills->get($row->id))
                    ? $bill->bill_no.' ('.Format::date($bill->period_from).' – '.Format::date($bill->period_to).')' : '',
                'adjustment' => (string) $adjustments->get($row->id, ''),
                default => (string) $payments->get($row->id, ''),
            };

            return $row;
        });
    }
}
