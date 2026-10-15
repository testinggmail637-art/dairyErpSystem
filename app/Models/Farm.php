<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksUser;
use App\Services\AppearanceService;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * A farm (business unit). Not farm-scoped itself; every business table points to it via farm_id.
 */
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksUser;

    /** Defaults for the `settings` JSON (spec Section 6, Phase 0). Stored values override these. */
    public const DEFAULT_SETTINGS = [
        'milk_drop_alert_percent' => 20,
        'allow_negative_milk_stock' => false,
        'farmer_payment_cycle_days' => 10,
        'cow_gestation_days' => 280,
        'buffalo_gestation_days' => 310,
        'vaccination_reminder_days' => 7,
        'calving_reminder_days' => 15,
        'staff_same_day_edit_only' => true,
        'bill_due_days' => 7,
        'delivery_sheet_generate_time' => '04:00',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'gstin',
        'timezone',
        'settings',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected static function booted(): void
    {
        // Name, address or appearance changed → rebuild the cached appearance (print header uses name/address).
        static::saved(fn (Farm $farm) => Cache::forget(AppearanceService::cacheKey($farm)));
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** One setting with its default, e.g. `$farm->setting('bill_due_days')` → 7. Dot notation allowed. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->allSettings(), $key, $default);
    }

    /** @return array<string, mixed> stored settings merged over the defaults */
    public function allSettings(): array
    {
        return array_replace_recursive(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /**
     * @param  Builder<Farm>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
