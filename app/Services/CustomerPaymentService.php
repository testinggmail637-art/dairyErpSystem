<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Customer payments (spec Section 6). Idempotent on client_uuid (spec 4.7); every change re-runs FIFO allocation
 * to bills in the same transaction.
 */
class CustomerPaymentService
{
    public function __construct(private readonly PaymentAllocationService $allocations) {}

    /**
     * @param  array{customer_id: int, payment_date: string, amount: string, payment_mode_id: int|string, reference_no?: ?string, note?: ?string, client_uuid?: ?string}  $data
     * @return array{0: CustomerPayment, 1: bool} the payment and whether it was newly created
     */
    public function record(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = CustomerPayment::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(function () use ($data, $uuid) {
                $payment = CustomerPayment::create($this->attributes($data) + [
                    'customer_id' => $data['customer_id'],
                    'collected_by' => auth()->id(),
                    'client_uuid' => $uuid,
                ]);
                $this->allocations->allocate($payment->customer_id);

                return $payment;
            }), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? CustomerPayment::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(CustomerPayment $payment, array $data): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment->update($this->attributes($data));
            $this->allocations->allocate($payment->customer_id);

            return $payment;
        });
    }

    public function delete(CustomerPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $payment->allocations()->delete();
            $payment->delete();
            $this->allocations->allocate($payment->customer_id);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'payment_date' => $data['payment_date'],
            'amount' => Money::round2($data['amount']),
            'payment_mode_id' => $data['payment_mode_id'],
            'reference_no' => $data['reference_no'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
