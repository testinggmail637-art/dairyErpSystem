<?php

namespace App\Observers;

use App\Models\Animal;
use App\Models\AnimalStatusHistory;
use App\Support\AuditReason;
use BackedEnum;

/**
 * Writes animal_status_histories (spec Section 6, Phase 2) whenever status, lifecycle stage or ownership changes —
 * from the status actions, the edit form or later modules (calving, sale). The date comes from
 * Animal::$changeDate (set by the caller) or today; the reason from the request's AuditReason.
 * A new animal gets one starting row per field.
 */
class AnimalObserver
{
    public function __construct(private readonly AuditReason $reason) {}

    public function created(Animal $animal): void
    {
        foreach (AnimalStatusHistory::FIELDS as $field) {
            $this->record($animal, $field, null);
        }
    }

    public function updated(Animal $animal): void
    {
        foreach (AnimalStatusHistory::FIELDS as $field) {
            if ($animal->wasChanged($field)) {
                $this->record($animal, $field, self::value($animal->getRawOriginal($field)));
            }
        }
    }

    private function record(Animal $animal, string $field, ?string $old): void
    {
        AnimalStatusHistory::create([
            'farm_id' => $animal->farm_id,
            'animal_id' => $animal->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => self::value($animal->getAttribute($field)),
            'changed_on' => $animal->changeDate ?? today()->toDateString(),
            'reason' => $this->reason->get(),
            'changed_by' => auth()->id(),
        ]);
    }

    private static function value(mixed $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }
}
