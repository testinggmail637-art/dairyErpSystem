<?php

namespace App\Services;

use App\Models\Party;
use App\Support\Money;

/** Animal trading parties (spec Phase 6). Balance, ledger and the "has purchases / sales" delete guard come with 6.2–6.4. */
class PartyService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): Party
    {
        return Party::create($this->attributes($data));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Party $party, array $data): Party
    {
        $party->update($this->attributes($data));

        return $party;
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
            'village' => $data['village'] ?? null,
            'address' => $data['address'] ?? null,
            'party_type' => $data['party_type'],
            'opening_balance' => Money::round2($data['opening_balance'] ?? '0'),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
