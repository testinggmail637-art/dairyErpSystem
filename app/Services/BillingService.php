<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DocumentType;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Customer;
use App\Models\Delivery;
use App\Support\CurrentFarm;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Billing (spec Section 6 "Billing rules"). Generate → draft holding every delivered, unbilled delivery of the period
 * (deliveries.bill_id set at once); finalize → number, balance snapshots, PDF, locked; cancel → deliveries released.
 */
class BillingService
{
    public const PDF_DISK = 'local';

    public function __construct(
        private readonly CurrentFarm $currentFarm,
        private readonly CustomerBalanceService $balances,
        private readonly DocumentNumberService $numbers,
        private readonly PaymentAllocationService $allocations,
    ) {}

    /** @throws DomainException when the period overlaps another bill or there is nothing to bill */
    public function generate(Customer $customer, string $from, string $to, string $billDate): Bill
    {
        return DB::transaction(function () use ($customer, $from, $to, $billDate) {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->value('id'); // one generation per customer at a time

            if ($this->overlaps($customer->id, $from, $to)) {
                throw new DomainException(__(':name already has a bill for part of this period.', ['name' => $customer->name]));
            }

            $deliveries = Delivery::query()->with('product:id,name')
                ->where('customer_id', $customer->id)
                ->where('status', DeliveryStatus::Delivered)
                ->whereNull('bill_id')
                ->whereBetween('delivery_date', [$from, $to])
                ->orderBy('delivery_date')->orderBy('shift')
                ->lockForUpdate()
                ->get();

            if ($deliveries->isEmpty()) {
                throw new DomainException(__(':name has no delivered, unbilled milk in this period.', ['name' => $customer->name]));
            }

            $subtotal = Money::sum($deliveries->pluck('amount')->all());
            $bill = Bill::create([
                'customer_id' => $customer->id,
                'period_from' => $from,
                'period_to' => $to,
                'bill_date' => $billDate,
                'due_date' => Carbon::parse($billDate)->addDays((int) $this->currentFarm->farm()->setting('bill_due_days'))->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => '0.00',
                'total' => $subtotal,
                'status' => BillStatus::Draft,
            ]);

            $now = now();
            BillItem::insert($deliveries->map(fn (Delivery $d) => [
                'farm_id' => $bill->farm_id,
                'bill_id' => $bill->id,
                'delivery_id' => $d->id,
                'product_id' => $d->product_id,
                'item_date' => $d->delivery_date->toDateString(),
                'shift' => $d->shift->value,
                'description' => $d->product->name.' · '.$d->shift->label(),
                'quantity' => $d->delivered_qty,
                'rate' => $d->rate,
                'amount' => $d->amount,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            Delivery::query()->whereKey($deliveries->modelKeys())->update(['bill_id' => $bill->id]);

            return $bill;
        });
    }

    /**
     * Customers with delivered, unbilled milk in the period and no overlapping bill (bulk generation).
     *
     * @return list<int>
     */
    public function billableCustomerIds(string $from, string $to): array
    {
        return Delivery::query()
            ->where('status', DeliveryStatus::Delivered)
            ->whereNull('bill_id')
            ->whereBetween('delivery_date', [$from, $to])
            ->whereNotExists(fn ($q) => $q->from('bills')
                ->whereColumn('bills.customer_id', 'deliveries.customer_id')
                ->where('bills.status', '!=', BillStatus::Cancelled->value)
                ->where('bills.period_from', '<=', $to)->where('bills.period_to', '>=', $from))
            ->distinct()
            ->orderBy('customer_id')
            ->pluck('customer_id')
            ->all();
    }

    /** Draft only: removes it and releases its deliveries. */
    public function deleteDraft(Bill $bill): void
    {
        $this->assertStatus($bill, BillStatus::Draft);

        DB::transaction(function () use ($bill) {
            Delivery::query()->where('bill_id', $bill->id)->update(['bill_id' => null]);
            $bill->items()->delete();
            $bill->delete();
        });
    }

    /** Number, balance snapshots (spec formula), locked; then the PDF is stored. */
    public function finalize(Bill $bill): Bill
    {
        $this->assertStatus($bill, BillStatus::Draft);

        DB::transaction(function () use ($bill) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertStatus($bill, BillStatus::Draft);

            $bill->fill($this->balancePreview($bill) + [
                'bill_no' => $this->numbers->next(DocumentType::Bill, $bill->bill_date),
                'status' => BillStatus::Finalized,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ])->save();

            $this->allocations->allocate($bill->customer_id); // advance payments go to the new bill
        });

        $bill->refresh();
        $this->storePdf($bill);

        return $bill;
    }

    /**
     * previous = ledger balance on the day before the period; payments / adjustments from period start to bill date;
     * closing = previous + total − payments + adjustments. Shown live on drafts, stored on finalize.
     *
     * @return array{previous_balance: string, payments_in_period: string, adjustments_in_period: string, closing_balance: string}
     */
    public function balancePreview(Bill $bill): array
    {
        $customer = $bill->customer;
        $from = $bill->period_from->toDateString();
        $billDate = $bill->bill_date->toDateString();

        $previous = $this->balances->balanceAsOf($customer, $bill->period_from->copy()->subDay()->toDateString());
        $payments = $this->balances->paymentsBetween($customer, $from, $billDate);
        $adjustments = $this->balances->adjustmentsBetween($customer, $from, $billDate);

        return [
            'previous_balance' => $previous,
            'payments_in_period' => $payments,
            'adjustments_in_period' => $adjustments,
            'closing_balance' => Money::add(Money::sub(Money::add($previous, $bill->total), $payments), $adjustments),
        ];
    }

    /** Finalized only (drafts are deleted): cancelled with a reason, its deliveries are free to bill again. */
    public function cancel(Bill $bill, string $reason): void
    {
        $this->assertStatus($bill, BillStatus::Finalized);

        DB::transaction(function () use ($bill, $reason) {
            Delivery::query()->where('bill_id', $bill->id)->update(['bill_id' => null]);
            $bill->items()->update(['delivery_id' => null]); // items stay as the cancelled bill's record
            $bill->update([
                'status' => BillStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);
            $this->allocations->allocate($bill->customer_id); // its payments go to other bills or back to advance
        });
    }

    /** Path of the stored PDF on PDF_DISK; made again when missing (a finalized bill never changes). */
    public function pdfPath(Bill $bill): string
    {
        if ($bill->pdf_path === null || ! Storage::disk(self::PDF_DISK)->exists($bill->pdf_path)) {
            $this->storePdf($bill);
        }

        return $bill->pdf_path;
    }

    public function renderPdf(Bill $bill): \Barryvdh\DomPDF\PDF
    {
        $bill->loadMissing(['customer', 'items.product:id,name']);

        return Pdf::loadView('pdf.bill', [
            'bill' => $bill,
            'rows' => $bill->dayRows(),
            'b' => $bill->isDraft() ? $this->balancePreview($bill)
                : $bill->only(['previous_balance', 'payments_in_period', 'adjustments_in_period', 'closing_balance']),
        ]);
    }

    private function storePdf(Bill $bill): void
    {
        $path = "bills/{$bill->farm_id}/".str_replace('/', '-', $bill->label()).'.pdf';
        Storage::disk(self::PDF_DISK)->put($path, $this->renderPdf($bill)->output());

        Bill::query()->whereKey($bill->id)->update(['pdf_path' => $path]); // not an audited change
        $bill->pdf_path = $path;
    }

    private function overlaps(int $customerId, string $from, string $to): bool
    {
        return Bill::query()->where('customer_id', $customerId)
            ->where('status', '!=', BillStatus::Cancelled)
            ->where('period_from', '<=', $to)->where('period_to', '>=', $from)
            ->exists();
    }

    private function assertStatus(Bill $bill, BillStatus $status): void
    {
        if ($bill->status !== $status) {
            throw new DomainException(__('Only a :status bill can do this; this one is :current.', [
                'status' => mb_strtolower($status->label()), 'current' => mb_strtolower($bill->status->label()),
            ]));
        }
    }
}
