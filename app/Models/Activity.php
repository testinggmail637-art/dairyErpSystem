<?php

namespace App\Models;

use App\Support\AuditReason;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Audit log entry. Adds the farm of the changed record and the user's reason (spec 4.4).
 */
class Activity extends BaseActivity
{
    /** Models using the Auditable trait — the audit screen's "Record type" filter. Add each new audited model here. */
    public const AUDITED_MODELS = [
        User::class,
        Role::class,
        Farm::class,
        Expense::class,
        VendorPayment::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Activity $activity) {
            if ($activity->farm_id === null) {
                $subject = $activity->subject;

                $activity->farm_id = match (true) {
                    $subject instanceof Farm => $subject->getKey(),
                    $subject !== null && $subject->getAttribute('farm_id') !== null => $subject->getAttribute('farm_id'),
                    default => null,
                };
            }

            $reason = app(AuditReason::class)->get();

            if ($reason !== null && ! $activity->properties->has('reason')) {
                $activity->properties = $activity->properties->put('reason', $reason);
            }
        });
    }

    public function reason(): ?string
    {
        return $this->properties->get('reason');
    }

    /**
     * Changed fields as rows [field, old, new] for the audit screen. Nested settings (JSON columns) are
     * flattened to dot keys and only the keys that actually changed are listed.
     *
     * @return list<array{0: string, 1: mixed, 2: mixed}>
     */
    public function diffRows(): array
    {
        $old = self::flatten((array) $this->properties->get('old', []));
        $new = self::flatten((array) $this->properties->get('attributes', []));

        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($this->event === 'updated' && $before === $after) {
                continue;
            }

            $rows[] = [$key, $before, $after];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $flat += self::flatten($value, $prefix.$key.'.');
            } else {
                $flat[$prefix.$key] = $value;
            }
        }

        return $flat;
    }
}
