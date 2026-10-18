<?php

namespace App\Services;

use App\Models\VendorPayment;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payments to vendors (spec 7.1). Each lowers the vendor's balance. Creating is idempotent on client_uuid
 * (spec 4.7): submitting the same form twice returns the first payment instead of paying twice.
 */
class VendorPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: VendorPayment, 1: bool} the payment and whether it was newly created
     */
    public function create(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = VendorPayment::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(fn () => VendorPayment::create($this->attributes($data) + ['client_uuid' => $uuid])), true];
        } catch (UniqueConstraintViolationException $e) {
            // Two submits raced past the check above: the unique index kept one; return it.
            $existing = $uuid ? VendorPayment::query()->where('client_uuid', $uuid)->first() : null;

            if ($existing === null) {
                throw $e;
            }

            return [$existing, false];
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(VendorPayment $payment, array $data): VendorPayment
    {
        DB::transaction(fn () => $payment->update($this->attributes($data)));

        return $payment;
    }

    public function delete(VendorPayment $payment): void
    {
        $payment->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'vendor_id' => $data['vendor_id'],
            'payment_date' => Carbon::parse($data['payment_date'])->toDateString(),
            'amount' => Money::round2($data['amount']),
            'payment_mode_id' => $data['payment_mode_id'],
            'reference_no' => $data['reference_no'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
