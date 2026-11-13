<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A vaccine the farm gives (spec Phase 5). interval_days → default next due date of a vaccination. */
class VaccineType extends Model
{
    use BelongsToFarm, TracksUser;

    /** Seeded for every farm; interval left empty — the owner sets it per local vet advice. */
    public const DEFAULTS = ['FMD', 'HS', 'BQ', 'Brucellosis', 'Theileriosis', 'Deworming'];

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'name', 'interval_days', 'notes', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['interval_days' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<AnimalVaccination, $this>
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(AnimalVaccination::class);
    }

    /**
     * @param  Builder<VaccineType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Create any missing default vaccines for $farm; existing rows (even renamed or inactive) are left alone. */
    public static function seedDefaultsFor(Farm $farm): void
    {
        foreach (self::DEFAULTS as $name) {
            static::allFarms()->firstOrCreate(['farm_id' => $farm->id, 'name' => $name], ['is_active' => true]);
        }
    }
}
