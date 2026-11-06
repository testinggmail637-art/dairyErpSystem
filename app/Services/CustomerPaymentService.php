<?php

namespace App\Services;

use App\Models\CustomerPayment;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

/** Customer payments (spec Section 6). Idempotent on client_uuid (spec 4.7). */
class CustomerPaymentService
{
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
            return [CustomerPayment::create([
                'customer_id' => $data['customer_id'],
                'payment_date' => $data['payment_date'],
                'amount' => Money::round2($data['amount']),
                'payment_mode_id' => $data['payment_mode_id'],
                'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null,
                'collected_by' => auth()->id(),
                'client_uuid' => $uuid,
            ]), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? CustomerPayment::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }
}
