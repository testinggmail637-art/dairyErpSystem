<?php

namespace App\Services;

use App\Enums\CycleStatus;
use App\Enums\DocumentType;
use App\Enums\FarmerPaymentStatus;
use App\Enums\QualityStatus;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\MilkCollection;
use App\Models\PaymentMode;
use App\Support\CurrentFarm;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Farmer payment cycles (spec Phase 4, FarmerPaymentService rules):
 * - create a cycle for a date range → a draft statement for every farmer with accepted, unlinked collections in it,
 *   and those collections are linked (locked); advance deduction defaults to min(outstanding advance, gross);
 * - while draft: change bonus / deductions (net = gross + bonus − advance − other ≥ 0), or delete the cycle (unlinks);
 * - finalize → read-only; then mark statements paid (one by one, or all as cash).
 * Cycles never overlap, so a collection can only be in one cycle.
 */
class FarmerPaymentService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly CurrentFarm $currentFarm,
    ) {}

    /**
     * Default range of the next cycle: the day after the last cycle (or the first unpaid collection), for
     * farmer_payment_cycle_days days, not past today.
     *
     * @return array{0: string, 1: string}
     */
    public function suggestedRange(): array
    {
        $days = (int) ($this->currentFarm->farm()?->setting('farmer_payment_cycle_days') ?? 10);
        $lastTo = FarmerPaymentCycle::query()->max('to_date');
        $from = $lastTo ? Carbon::parse($lastTo)->addDay()
            : Carbon::parse(MilkCollection::query()->whereNull('farmer_payment_id')->min('collection_date') ?? today()->subDays($days - 1));
        $to = $from->copy()->addDays($days - 1);

        return [$from->toDateString(), min($to, today())->toDateString()];
    }

    public function createCycle(string $from, string $to): FarmerPaymentCycle
    {
        return DB::transaction(function () use ($from, $to) {
            $this->lockFarm();
            if ($other = $this->overlapping($from, $to)) {
                throw new DomainException(__('Cycle :no already covers :from – :to. Cycles cannot overlap.', [
                    'no' => $other->cycle_no, 'from' => fmt_date($other->from_date), 'to' => fmt_date($other->to_date),
                ]));
            }

            $accepted = QualityStatus::Accepted->value;
            $lines = MilkCollection::query()->toBase()
                ->where('farm_id', $this->currentFarm->id())
                ->whereBetween('collection_date', [$from, $to])->where('quality_status', $accepted)->whereNull('farmer_payment_id')
                ->groupBy('farmer_id')
                ->selectRaw('farmer_id, SUM(quantity_litre) AS qty, SUM(amount) AS gross')
                ->selectRaw('SUM(CASE WHEN fat IS NOT NULL THEN fat * quantity_litre END) AS fat_l, SUM(CASE WHEN fat IS NOT NULL THEN quantity_litre END) AS fat_q')
                ->selectRaw('SUM(CASE WHEN snf IS NOT NULL THEN snf * quantity_litre END) AS snf_l, SUM(CASE WHEN snf IS NOT NULL THEN quantity_litre END) AS snf_q')
                ->get();
            if ($lines->isEmpty()) {
                throw new DomainException(__('No accepted milk waiting for payment between :from and :to.', ['from' => fmt_date($from), 'to' => fmt_date($to)]));
            }

            $cycle = FarmerPaymentCycle::create([
                'cycle_no' => $this->numbers->next(DocumentType::FarmerPayment, Carbon::parse($to)),
                'from_date' => $from, 'to_date' => $to, 'status' => CycleStatus::Draft,
            ]);
            $advances = $this->outstandingAdvances($lines->pluck('farmer_id')->all());
            $avg = fn ($sum, $qty) => $qty !== null && Money::compare((string) $qty, '0') > 0 ? Money::div((string) $sum, (string) $qty) : null;

            foreach ($lines as $line) {
                $gross = Money::round2((string) $line->gross);
                $outstanding = $advances[$line->farmer_id] ?? '0.00';
                $advance = Money::compare($outstanding, $gross) < 0 ? $outstanding : $gross;
                $cycle->payments()->create([
                    'farmer_id' => $line->farmer_id, 'total_qty' => Money::round2((string) $line->qty),
                    'avg_fat' => $avg($line->fat_l, $line->fat_q), 'avg_snf' => $avg($line->snf_l, $line->snf_q),
                    'gross_amount' => $gross, 'advance_deduction' => Money::compare($advance, '0') > 0 ? $advance : '0.00',
                    'net_payable' => Money::sub($gross, Money::compare($advance, '0') > 0 ? $advance : '0'), 'status' => FarmerPaymentStatus::Pending,
                ]);
            }

            // lock the collections: one UPDATE … JOIN for the whole cycle
            DB::table('milk_collections as c')
                ->join('farmer_payments as p', fn ($j) => $j->on('p.farmer_id', '=', 'c.farmer_id')->where('p.farmer_payment_cycle_id', $cycle->id))
                ->where('c.farm_id', $this->currentFarm->id())
                ->whereBetween('c.collection_date', [$from, $to])->where('c.quality_status', $accepted)->whereNull('c.farmer_payment_id')
                ->update(['c.farmer_payment_id' => DB::raw('p.id')]);

            return $cycle;
        });
    }

    /**
     * Change bonus / deductions of a draft statement.
     *
     * @param  array{bonus?: string|null, advance_deduction?: string|null, other_deduction?: string|null, deduction_note?: string|null}  $data
     */
    public function updateStatement(FarmerPayment $payment, array $data): FarmerPayment
    {
        return DB::transaction(function () use ($payment, $data) {
            $this->ensureDraft($payment->cycle);
            $bonus = Money::round2($data['bonus'] ?? '0');
            $advance = Money::round2($data['advance_deduction'] ?? '0');
            $other = Money::round2($data['other_deduction'] ?? '0');

            $outstanding = $this->outstandingAdvances([$payment->farmer_id])[$payment->farmer_id] ?? '0.00';
            if (Money::compare($advance, $outstanding) > 0) {
                throw new DomainException(__('Only :amount of advance is still to be recovered from this farmer.', ['amount' => inr($outstanding)]));
            }
            $net = Money::sub(Money::add($payment->gross_amount, $bonus), Money::add($advance, $other));
            if (Money::isNegative($net)) {
                throw new DomainException(__('Deductions are more than the gross + bonus: net would be :net.', ['net' => inr($net)]));
            }

            $payment->update(['bonus' => $bonus, 'advance_deduction' => $advance, 'other_deduction' => $other,
                'deduction_note' => $data['deduction_note'] ?? null, 'net_payable' => $net]);

            return $payment;
        });
    }

    /** Delete a draft cycle: its collections are unlinked (editable again) and its statements removed. */
    public function deleteCycle(FarmerPaymentCycle $cycle): void
    {
        DB::transaction(function () use ($cycle) {
            $this->ensureDraft($cycle);
            MilkCollection::query()->whereIn('farmer_payment_id', $cycle->payments()->select('id'))->update(['farmer_payment_id' => null]);
            $cycle->payments()->each(fn (FarmerPayment $p) => $p->delete()); // one by one: audit log per statement
            $cycle->delete();
        });
    }

    public function finalize(FarmerPaymentCycle $cycle): void
    {
        DB::transaction(function () use ($cycle) {
            $this->ensureDraft($cycle);
            // another cycle may have taken the same advance since this draft was made
            $payments = $cycle->payments()->with('farmer:id,code,name')->where('advance_deduction', '>', 0)->get();
            $outstanding = $this->outstandingAdvances($payments->pluck('farmer_id')->all());
            foreach ($payments as $p) {
                if (Money::compare($p->advance_deduction, $outstanding[$p->farmer_id] ?? '0') > 0) {
                    throw new DomainException(__(':code :name: only :amount of advance is left to recover. Change the advance deduction first.', [
                        'code' => $p->farmer->code, 'name' => $p->farmer->name, 'amount' => inr($outstanding[$p->farmer_id] ?? '0'),
                    ]));
                }
            }
            $cycle->update(['status' => CycleStatus::Finalized, 'finalized_at' => now(), 'finalized_by' => auth()->id()]);
        });
    }

    /** @param  array{paid_on: string, payment_mode_id: int|string, reference_no?: string|null}  $data */
    public function markPaid(FarmerPayment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $this->ensurePayable($payment);
            $payment->update(['status' => FarmerPaymentStatus::Paid, 'paid_on' => $data['paid_on'],
                'payment_mode_id' => $data['payment_mode_id'], 'reference_no' => $data['reference_no'] ?? null]);
        });
    }

    /** Undo a wrong "paid" (with a reason in the audit log). */
    public function markUnpaid(FarmerPayment $payment): void
    {
        $payment->update(['status' => FarmerPaymentStatus::Pending, 'paid_on' => null, 'payment_mode_id' => null, 'reference_no' => null]);
    }

    /** Mark every pending statement of a finalized cycle paid in cash on a date. Returns how many. */
    public function markAllPaidCash(FarmerPaymentCycle $cycle, string $paidOn): int
    {
        return DB::transaction(function () use ($cycle, $paidOn) {
            if ($cycle->isDraft()) {
                throw new DomainException(__('Finalize the cycle before paying.'));
            }
            $cash = PaymentMode::query()->where('code', 'CASH')->value('id')
                ?? throw new DomainException(__('There is no "Cash" payment mode. Add it in Settings → Payment Modes.'));
            $count = 0;
            $cycle->payments()->where('status', FarmerPaymentStatus::Pending)->each(function (FarmerPayment $p) use ($paidOn, $cash, &$count) {
                $p->update(['status' => FarmerPaymentStatus::Paid, 'paid_on' => $paidOn, 'payment_mode_id' => $cash]);
                $count++;
            });

            return $count;
        });
    }

    /**
     * Advance still to recover per farmer: Σ advances − Σ advance deducted on finalized statements (two grouped queries).
     *
     * @param  list<int>  $farmerIds
     * @return array<int, string>
     */
    public function outstandingAdvances(array $farmerIds): array
    {
        if ($farmerIds === []) {
            return [];
        }
        $given = FarmerAdvance::query()->whereIn('farmer_id', $farmerIds)->groupBy('farmer_id')
            ->selectRaw('farmer_id, SUM(amount) AS total')->pluck('total', 'farmer_id');
        $recovered = FarmerPayment::query()->whereIn('farmer_id', $farmerIds)
            ->whereHas('cycle', fn ($q) => $q->where('status', CycleStatus::Finalized))
            ->groupBy('farmer_id')->selectRaw('farmer_id, SUM(advance_deduction) AS total')->pluck('total', 'farmer_id');

        return collect($farmerIds)->mapWithKeys(fn ($id) => [$id => Money::sub((string) ($given[$id] ?? '0'), (string) ($recovered[$id] ?? '0'))])->all();
    }

    /**
     * What we owe the farmer now (spec): opening + Σ finalized (gross + bonus − other deduction) − Σ advances − Σ net paid.
     */
    public function balance(Farmer $farmer): string
    {
        $finalized = FarmerPayment::query()->where('farmer_id', $farmer->id)
            ->whereHas('cycle', fn ($q) => $q->where('status', CycleStatus::Finalized))
            ->selectRaw('COALESCE(SUM(gross_amount + bonus - other_deduction), 0) AS earned')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN net_payable END), 0) AS paid', [FarmerPaymentStatus::Paid->value])
            ->toBase()->first();
        $advances = (string) FarmerAdvance::query()->where('farmer_id', $farmer->id)->sum('amount');

        return Money::sub(Money::add($farmer->opening_balance, (string) $finalized->earned), Money::add($advances, (string) $finalized->paid));
    }

    /** The cycle whose period holds this date, if any — collections on that date cannot be added or changed. */
    public function cycleOn(string $date): ?FarmerPaymentCycle
    {
        return FarmerPaymentCycle::query()->where('from_date', '<=', $date)->where('to_date', '>=', $date)->first();
    }

    private function overlapping(string $from, string $to): ?FarmerPaymentCycle
    {
        return FarmerPaymentCycle::query()->where('from_date', '<=', $to)->where('to_date', '>=', $from)->first();
    }

    private function ensureDraft(FarmerPaymentCycle $cycle): void
    {
        if (FarmerPaymentCycle::query()->whereKey($cycle->id)->lockForUpdate()->toBase()->value('status') !== CycleStatus::Draft->value) {
            throw new DomainException(__('Cycle :no is finalized and cannot be changed.', ['no' => $cycle->cycle_no]));
        }
    }

    private function ensurePayable(FarmerPayment $payment): void
    {
        if ($payment->cycle->isDraft()) {
            throw new DomainException(__('Finalize the cycle before paying.'));
        }
        if ($payment->isPaid()) {
            throw new DomainException(__('This statement is already paid.'));
        }
    }

    private function lockFarm(): void
    {
        DB::table('farms')->where('id', $this->currentFarm->id())->lockForUpdate()->value('id');
    }
}
