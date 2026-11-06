<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentAllocation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * FIFO allocation (spec Section 6): payment money (oldest payment first) is set against finalized bills (oldest bill
 * first). Money left over stays as advance and is used when the next bill is finalized.
 *
 * The customer's allocations are rebuilt from scratch on every change that can move money (payment saved / edited /
 * deleted, bill finalized / cancelled), so the result is always strict FIFO. Two queries + one insert per customer.
 */
class PaymentAllocationService
{
    public function allocate(int $customerId): void
    {
        DB::transaction(function () use ($customerId) {
            Customer::withTrashed()->whereKey($customerId)->lockForUpdate()->value('id');

            $payments = CustomerPayment::query()->where('customer_id', $customerId)
                ->orderBy('payment_date')->orderBy('id')->get(['id', 'farm_id', 'amount']);

            PaymentAllocation::query()->whereIn('customer_payment_id', $payments->modelKeys())->delete();

            $bills = Bill::query()->where('customer_id', $customerId)->where('status', BillStatus::Finalized)
                ->orderBy('bill_date')->orderBy('id')->get(['id', 'total']);

            $rows = [];
            $now = now();
            $b = 0;
            $billLeft = $bills[0]->total ?? '0';

            foreach ($payments as $payment) {
                $left = $payment->amount;

                while (Money::compare($left, '0') > 0 && isset($bills[$b])) {
                    $take = Money::compare($left, $billLeft) < 0 ? $left : $billLeft;

                    if (Money::compare($take, '0') > 0) {
                        $rows[] = ['farm_id' => $payment->farm_id, 'customer_payment_id' => $payment->id, 'bill_id' => $bills[$b]->id,
                            'amount' => $take, 'created_at' => $now, 'updated_at' => $now];
                    }

                    $left = Money::sub($left, $take);
                    $billLeft = Money::sub($billLeft, $take);

                    if (Money::compare($billLeft, '0') <= 0) {
                        $billLeft = $bills[++$b]->total ?? '0';
                    }
                }
            }

            if ($rows !== []) {
                PaymentAllocation::insert($rows);
            }
        });
    }
}
