<?php

namespace App\Services;

use App\Models\Vendor;
use App\Support\Money;

/**
 * Vendors (spec Section 6, Phase 1). Balances (opening + credit expenses − payments) arrive with Task 1.5.
 */
class VendorService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): Vendor
    {
        return Vendor::create($this->attributes($data));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Vendor $vendor, array $data): Vendor
    {
        $vendor->update($this->attributes($data));

        return $vendor;
    }

    /** Soft delete (master data). Expenses and payments (Tasks 1.3 / 1.5) will block deleting a vendor in use. */
    public function delete(Vendor $vendor): void
    {
        $vendor->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'gstin' => $data['gstin'] ?? null,
            'opening_balance' => Money::round2($data['opening_balance'] ?? '0'),
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
