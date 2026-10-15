<?php

namespace App\Models\Concerns;

use App\Models\Farm;

/**
 * Master data with default rows per farm, listed in the model's `DEFAULTS` constant (code => name).
 * Use with BelongsToFarm.
 */
trait HasFarmDefaults
{
    /** Create any missing default rows for $farm; existing rows (even renamed or inactive) are left alone. */
    public static function seedDefaultsFor(Farm $farm): void
    {
        foreach (static::DEFAULTS as $code => $name) {
            static::allFarms()->firstOrCreate(
                ['farm_id' => $farm->id, 'code' => $code],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
