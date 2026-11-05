<?php

namespace App\Services;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\CustomerRate;
use App\Models\Farm;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Customers and their own rates (spec Section 6, Phase 3). */
class CustomerService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Customer
    {
        return DB::transaction(fn () => Customer::create($this->attributes($data) + ['code' => $this->nextCode()]));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($this->attributes($data));

        return $customer;
    }

    /** Soft delete (master data); the code stays used. Deliveries and bills will block this in later tasks. */
    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    /** @param  array{product_id: int|string, rate: string, effective_from: string}  $data */
    public function addRate(Customer $customer, array $data): CustomerRate
    {
        return $customer->rates()->create([
            'product_id' => $data['product_id'],
            'rate' => Money::round2($data['rate']),
            'effective_from' => $data['effective_from'],
        ]);
    }

    /** Next C-0001 style code of the current farm; the farm row lock makes two saves at once get different codes. */
    private function nextCode(): string
    {
        $farmId = $this->currentFarm->id();

        Farm::query()->whereKey($farmId)->lockForUpdate()->value('id');

        $last = Customer::allFarms()->withTrashed()
            ->where('farm_id', $farmId)
            ->max(DB::raw('CAST(SUBSTRING(code, 3) AS UNSIGNED)'));

        return sprintf('C-%04d', ((int) $last) + 1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $isB2b = ($data['customer_type'] ?? null) === CustomerType::B2b->value;
        $routeId = $data['route_id'] ?? null;

        return [
            'name' => $data['name'],
            'customer_type' => $data['customer_type'],
            'b2b_category' => $isB2b ? $data['b2b_category'] : null,
            'phone' => $data['phone'] ?? null,
            'alt_phone' => $data['alt_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'area' => $data['area'] ?? null,
            'route_id' => $routeId,
            'route_sequence' => $routeId ? ($data['route_sequence'] ?? null) : null,
            'gstin' => $data['gstin'] ?? null,
            'opening_balance' => Money::round2($data['opening_balance'] ?? '0'),
            'credit_limit' => isset($data['credit_limit']) ? Money::round2($data['credit_limit']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
