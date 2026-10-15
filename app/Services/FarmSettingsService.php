<?php

namespace App\Services;

use App\Models\Farm;

class FarmSettingsService
{
    /**
     * Save farm details and settings. Setting values are cast to the type of their default
     * (int/bool/string); keys not on the form (e.g. `appearance`) are kept.
     *
     * @param  array{name: string, address: ?string, phone: ?string, gstin: ?string, settings: array<string, mixed>}  $data
     */
    public function update(Farm $farm, array $data): Farm
    {
        $settings = $farm->settings ?? [];

        foreach (Farm::DEFAULT_SETTINGS as $key => $default) {
            if (array_key_exists($key, $data['settings'])) {
                $value = $data['settings'][$key];
                $settings[$key] = match (true) {
                    is_bool($default) => (bool) $value,
                    is_int($default) => (int) $value,
                    default => (string) $value,
                };
            }
        }

        $farm->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'gstin' => $data['gstin'] ?? null,
            'settings' => $settings,
        ]);

        return $farm;
    }
}
