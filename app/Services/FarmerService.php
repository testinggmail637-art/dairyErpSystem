<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Farmer;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Farmers of the collection center (spec Phase 4). */
class FarmerService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Farmer
    {
        return DB::transaction(fn () => Farmer::create($this->attributes($data) + ['code' => $this->nextCode()]));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Farmer $farmer, array $data): Farmer
    {
        $farmer->update($this->attributes($data));

        return $farmer;
    }

    /** Soft delete (master data); the code stays used. Collections and payments will block this in later tasks. */
    public function delete(Farmer $farmer): void
    {
        $farmer->delete();
    }

    /** Next F-001 style code of the current farm; the farm row lock makes two saves at once get different codes. */
    private function nextCode(): string
    {
        $farmId = $this->currentFarm->id();

        Farm::query()->whereKey($farmId)->lockForUpdate()->value('id');

        $last = Farmer::allFarms()->withTrashed()
            ->where('farm_id', $farmId)
            ->max(DB::raw('CAST(SUBSTRING(code, 3) AS UNSIGNED)'));

        return Farmer::codeFor(((int) $last) + 1);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'father_name' => $data['father_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'village' => $data['village'] ?? null,
            'address' => $data['address'] ?? null,
            'default_milk_type' => $data['default_milk_type'],
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'ifsc' => $data['ifsc'] ?? null,
            'upi_id' => $data['upi_id'] ?? null,
            'opening_balance' => Money::round2($data['opening_balance'] ?? '0'),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];
    }
}
