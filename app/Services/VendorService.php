<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Vendor;
use App\Support\Money;
use DomainException;

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

    /**
     * Soft delete (master data) — only when no expense uses the vendor (vendor payments will block it too, Task 1.5).
     *
     * @throws DomainException
     */
    public function delete(Vendor $vendor): void
    {
        if (Expense::withTrashed()->where('vendor_id', $vendor->id)->exists() || $vendor->payments()->exists()) {
            throw new DomainException(__('This vendor has expenses or payments, so it cannot be deleted. Deactivate it instead.'));
        }

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
