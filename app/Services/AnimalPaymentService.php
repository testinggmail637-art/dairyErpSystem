<?php

namespace App\Services;

use App\Models\AnimalPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Payments to / from animal trading parties (spec Phase 6). Idempotent on client_uuid (spec 4.7). */
class AnimalPaymentService
{
    /**
     * @param  array<string, mixed>  $data  validated payment fields
     * @return array{0: AnimalPayment, 1: bool} the payment and whether it was newly created
     */
    public function record(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = AnimalPayment::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(fn () => AnimalPayment::create($data)), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? AnimalPayment::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }

    /** Wrong entries are deleted (reason from the request, audit-logged) and entered again. */
    public function delete(AnimalPayment $payment): void
    {
        DB::transaction(fn () => $payment->delete());
    }
}
