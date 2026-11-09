<?php

namespace App\Services;

use App\Models\FarmerAdvance;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Farmer advances (spec Phase 4). Outstanding advance = Σ advances − Σ advance deducted on finalized payment
 * statements; the deduction side arrives with payment cycles (Task 4.6).
 */
class FarmerAdvanceService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: FarmerAdvance, 1: bool} the advance and whether it was newly created (client_uuid, spec 4.7)
     */
    public function create(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;
        if ($uuid && ($existing = FarmerAdvance::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(fn () => FarmerAdvance::create([
                'farmer_id' => $data['farmer_id'], 'advance_date' => $data['advance_date'], 'amount' => Money::round2($data['amount']),
                'payment_mode_id' => $data['payment_mode_id'], 'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null, 'client_uuid' => $uuid,
            ])), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? FarmerAdvance::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }

    public function delete(FarmerAdvance $advance): void
    {
        $advance->delete();
    }

    /** Advance still to be recovered from this farmer. */
    public function outstanding(int $farmerId): string
    {
        return Money::round2((string) FarmerAdvance::query()->where('farmer_id', $farmerId)->sum('amount'));
    }
}
