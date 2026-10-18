<?php

namespace App\Services;

use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendor ledger (spec 7.1): opening balance + credit expenses (we owe more) − vendor payments (we owe less),
 * with a running balance. Paid (non-credit) expenses never touch it.
 *
 * Built for years of entries: sums and the running balance (a window function) run on a lean list taken from
 * covering indexes (date, amount, id only); names and notes are fetched afterwards for the rows on the page.
 */
class VendorLedgerService
{
    public const PER_PAGE = 100;

    /** Balance at the start of $from (null = the opening balance itself). */
    public function balanceBefore(Vendor $vendor, ?string $from): string
    {
        if ($from === null) {
            return Money::round2($vendor->opening_balance);
        }

        $row = DB::query()->fromSub($this->entries($vendor, null, $from, false), 'e')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        return Money::sub(Money::add($vendor->opening_balance, (string) $row->debit), (string) $row->credit);
    }

    /**
     * @return array{opening: string, debit: string, credit: string, closing: string, rows: LengthAwarePaginator}
     */
    public function ledger(Vendor $vendor, ?string $from, ?string $to, int $page = 1, array $query = []): array
    {
        $opening = $this->balanceBefore($vendor, $from);
        $entries = $this->entries($vendor, $from, $to, true);

        $totals = DB::query()->fromSub($entries, 'e')
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        $rows = DB::query()->fromSub($entries, 'e')
            ->select('e.*')
            // CAST keeps it exact: a bound string added to a DECIMAL would otherwise become a DOUBLE.
            ->selectRaw('CAST(? AS DECIMAL(14,2)) + SUM(debit - credit) OVER (ORDER BY entry_date, sort_key, id ROWS UNBOUNDED PRECEDING) AS balance', [$opening])
            ->orderBy('entry_date')->orderBy('sort_key')->orderBy('id')
            ->forPage($page, self::PER_PAGE)
            ->get();

        $debit = Money::round2((string) $totals->debit);
        $credit = Money::round2((string) $totals->credit);

        return [
            'opening' => $opening,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => Money::sub(Money::add($opening, $debit), $credit),
            'rows' => new LengthAwarePaginator($this->withDetails($rows), (int) $totals->cnt, self::PER_PAGE, $page, [
                'path' => request()->url(),
                'query' => $query,
            ]),
        ];
    }

    /**
     * Credit expenses and payments of one vendor as one lean list (entry_date, sort_key, id, type, debit, credit),
     * served from covering indexes. Purchases sort before payments on the same day.
     * $inclusiveFrom=false gives everything strictly before $to (for the balance before a date).
     */
    private function entries(Vendor $vendor, ?string $from, ?string $to, bool $inclusiveFrom): Builder
    {
        $range = function (Builder $q, string $column) use ($from, $to, $inclusiveFrom) {
            $q->when($from, fn ($q) => $q->where($column, '>=', $from))
                ->when($to, fn ($q) => $inclusiveFrom ? $q->where($column, '<=', $to) : $q->where($column, '<', $to));
        };

        $expenses = DB::table('expenses')
            ->where('farm_id', $vendor->farm_id)
            ->where('vendor_id', $vendor->id)
            ->whereNull('deleted_at')
            ->where('is_credit', true)
            ->tap(fn ($q) => $range($q, 'expense_date'))
            ->selectRaw("expense_date AS entry_date, 1 AS sort_key, id, 'expense' AS type, amount AS debit, 0 AS credit");

        $payments = DB::table('vendor_payments')
            ->where('farm_id', $vendor->farm_id)
            ->where('vendor_id', $vendor->id)
            ->tap(fn ($q) => $range($q, 'payment_date'))
            ->selectRaw("payment_date AS entry_date, 2 AS sort_key, id, 'payment' AS type, 0 AS debit, amount AS credit");

        return $expenses->unionAll($payments);
    }

    /**
     * Adds detail (category / payment mode) and note to the page's rows with two small keyed queries.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function withDetails(Collection $rows): Collection
    {
        $expenseIds = $rows->where('type', 'expense')->pluck('id')->all();
        $paymentIds = $rows->where('type', 'payment')->pluck('id')->all();

        $expenses = $expenseIds ? DB::table('expenses')
            ->leftJoin('expense_categories as c', 'c.id', '=', 'expenses.category_id')
            ->whereIn('expenses.id', $expenseIds)
            ->selectRaw("expenses.id AS id, CONCAT_WS('\t', COALESCE(c.name, ''), COALESCE(expenses.note, '')) AS info")
            ->pluck('info', 'id') : collect();

        $payments = $paymentIds ? DB::table('vendor_payments')
            ->leftJoin('payment_modes as m', 'm.id', '=', 'vendor_payments.payment_mode_id')
            ->whereIn('vendor_payments.id', $paymentIds)
            ->selectRaw("vendor_payments.id AS id, CONCAT_WS('\t', COALESCE(m.name, ''), CONCAT_WS(' · ', vendor_payments.reference_no, vendor_payments.note)) AS info")
            ->pluck('info', 'id') : collect();

        return $rows->map(function (object $row) use ($expenses, $payments) {
            [$row->detail, $row->note] = array_pad(explode("\t", (string) ($row->type === 'expense' ? $expenses : $payments)->get($row->id, '')), 2, '');

            return $row;
        });
    }
}
