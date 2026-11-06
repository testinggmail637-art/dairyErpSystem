<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\Money;

/**
 * Customer ledger balance (spec Section 6): opening + Σ finalized bills (bill_date ≤ D) + Σ adjustments (≤ D)
 * − Σ payments (≤ D). All sums in SQL. Customer adjustments arrive with Task 3.11; until then they add 0.
 */
class CustomerBalanceService
{
    public function balanceAsOf(Customer $customer, string $date): string
    {
        $bills = Bill::query()->where('customer_id', $customer->id)->where('status', BillStatus::Finalized)
            ->where('bill_date', '<=', $date)->sum('total');

        return Money::sub(
            Money::add(Money::add($customer->opening_balance, (string) $bills), $this->adjustmentsBetween($customer, null, $date)),
            $this->paymentsBetween($customer, null, $date),
        );
    }

    public function paymentsBetween(Customer $customer, ?string $from, string $to): string
    {
        return Money::round2((string) CustomerPayment::query()->where('customer_id', $customer->id)
            ->when($from, fn ($q) => $q->where('payment_date', '>=', $from))
            ->where('payment_date', '<=', $to)
            ->sum('amount'));
    }

    public function adjustmentsBetween(Customer $customer, ?string $from, string $to): string
    {
        return '0.00'; // customer_adjustments table comes with Task 3.11
    }
}
