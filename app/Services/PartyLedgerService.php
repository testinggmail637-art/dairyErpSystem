<?php

namespace App\Services;

use App\Enums\AnimalSaleStatus;
use App\Enums\PaymentDirection;
use App\Models\Party;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Party balance and ledger (spec Phase 6): + = the party owes us.
 * Balance = opening + Σ sales net (not cancelled) − Σ purchases total − Σ received + Σ paid.
 * Built like CustomerLedgerService: SQL sums and a window-function running balance over a lean UNION.
 */
class PartyLedgerService
{
    public const PER_PAGE = 100;

    /**
     * Current balances of many parties in three GROUP BY queries (list screen).
     *
     * @param  Collection<int, Party>  $parties
     * @return array<int, string> party id => balance
     */
    public function balances(Collection $parties): array
    {
        $ids = $parties->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $sales = DB::table('animal_sales')->whereIn('party_id', $ids)->where('status', AnimalSaleStatus::Completed->value)
            ->groupBy('party_id')->pluck(DB::raw('SUM(net_amount)'), 'party_id');
        $purchases = DB::table('animal_purchases')->whereIn('party_id', $ids)
            ->groupBy('party_id')->pluck(DB::raw('SUM(total_amount)'), 'party_id');
        $payments = DB::table('animal_payments')->whereIn('party_id', $ids)->groupBy('party_id')
            ->selectRaw('party_id, SUM(CASE WHEN direction = ? THEN amount ELSE -amount END) AS net', [PaymentDirection::Paid->value])
            ->pluck('net', 'party_id');

        return $parties->mapWithKeys(fn (Party $p) => [$p->id => Money::add(
            $p->opening_balance,
            (string) ($sales[$p->id] ?? 0),
            Money::sub(0, (string) ($purchases[$p->id] ?? 0)),
            (string) ($payments[$p->id] ?? 0),
        )])->all();
    }

    public function balance(Party $party, ?string $before = null): string
    {
        $row = DB::query()->fromSub($this->entries($party, null, $before, false), 'e')
            ->selectRaw('COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        return Money::sub(Money::add($party->opening_balance, (string) $row->debit), (string) $row->credit);
    }

    /**
     * @param  array<string, mixed>  $query  kept on page links
     * @return array{opening: string, debit: string, credit: string, closing: string, rows: LengthAwarePaginator}
     */
    public function ledger(Party $party, ?string $from, ?string $to, int $page = 1, array $query = []): array
    {
        $opening = $from ? $this->balance($party, $from) : Money::round2($party->opening_balance);
        $entries = $this->entries($party, $from, $to, true);

        $totals = DB::query()->fromSub($entries, 'e')
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(debit), 0) AS debit, COALESCE(SUM(credit), 0) AS credit')
            ->first();

        $rows = DB::query()->fromSub($entries, 'e')
            ->select('e.*')
            // CAST keeps it exact: a bound string added to a DECIMAL would otherwise become a DOUBLE.
            ->selectRaw('CAST(? AS DECIMAL(14,2)) + SUM(debit - credit) OVER (ORDER BY entry_date, sort_key, id ROWS UNBOUNDED PRECEDING) AS balance', [$opening])
            ->orderBy('entry_date')->orderBy('sort_key')->orderBy('id')
            ->forPage($page, self::PER_PAGE)->get();

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

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'sale' => __('Sale'),
            'purchase' => __('Purchase'),
            'paid' => __('Paid to party'),
            default => __('Received from party'),
        };
    }

    /**
     * Sales (debit), purchases (credit), payments paid (debit) / received (credit) as one lean list.
     * Same day: sale / purchase first, then payments. $inclusiveFrom=false gives everything strictly before $to.
     */
    private function entries(Party $party, ?string $from, ?string $to, bool $inclusiveFrom): Builder
    {
        $range = function (Builder $q, string $column) use ($from, $to, $inclusiveFrom) {
            $q->when($from, fn ($q) => $q->where($column, '>=', $from))
                ->when($to, fn ($q) => $inclusiveFrom ? $q->where($column, '<=', $to) : $q->where($column, '<', $to));
        };

        $sales = DB::table('animal_sales')->where('party_id', $party->id)->where('status', AnimalSaleStatus::Completed->value)
            ->tap(fn ($q) => $range($q, 'sale_date'))
            ->selectRaw("sale_date AS entry_date, 1 AS sort_key, id, 'sale' AS type, net_amount AS debit, 0 AS credit");

        $purchases = DB::table('animal_purchases')->where('party_id', $party->id)
            ->tap(fn ($q) => $range($q, 'purchase_date'))
            ->selectRaw("purchase_date AS entry_date, 1 AS sort_key, id, 'purchase' AS type, 0 AS debit, total_amount AS credit");

        $payments = DB::table('animal_payments')->where('party_id', $party->id)
            ->tap(fn ($q) => $range($q, 'payment_date'))
            ->selectRaw('payment_date AS entry_date, 2 AS sort_key, id, direction AS type, '
                .'CASE WHEN direction = ? THEN amount ELSE 0 END AS debit, CASE WHEN direction = ? THEN 0 ELSE amount END AS credit',
                [PaymentDirection::Paid->value, PaymentDirection::Paid->value]);

        return $sales->unionAll($purchases)->unionAll($payments);
    }

    /**
     * Sale / purchase numbers and payment mode + reference for the rows shown (three small keyed queries).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function withDetails(Collection $rows): Collection
    {
        $ids = fn (string ...$types) => $rows->whereIn('type', $types)->pluck('id')->all();

        $sales = ($s = $ids('sale')) ? DB::table('animal_sales')->whereIn('id', $s)->pluck('sale_no', 'id') : collect();
        $purchases = ($p = $ids('purchase')) ? DB::table('animal_purchases')->whereIn('id', $p)->pluck('purchase_no', 'id') : collect();
        $payments = ($m = $ids('paid', 'received')) ? DB::table('animal_payments as ap')
            ->leftJoin('payment_modes as m', 'm.id', '=', 'ap.payment_mode_id')
            ->leftJoin('animal_purchases as pu', 'pu.id', '=', 'ap.animal_purchase_id')
            ->leftJoin('animal_sales as sa', 'sa.id', '=', 'ap.animal_sale_id')
            ->whereIn('ap.id', $m)
            ->selectRaw("ap.id AS id, CONCAT_WS(' · ', m.name, COALESCE(pu.purchase_no, sa.sale_no), ap.reference_no, ap.note) AS info")
            ->pluck('info', 'id') : collect();

        return $rows->map(function (object $row) use ($sales, $purchases, $payments) {
            $row->detail = match ($row->type) {
                'sale' => (string) $sales->get($row->id, ''),
                'purchase' => (string) $purchases->get($row->id, ''),
                default => (string) $payments->get($row->id, ''),
            };

            return $row;
        });
    }
}
