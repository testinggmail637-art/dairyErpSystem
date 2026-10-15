<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\HasFarmDefaults;
use App\Models\Concerns\TracksUser;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Business section of the farm (Own Herd, Collection Center, …) used to tag expenses and reports.
 */
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use BelongsToFarm, HasFactory, HasFarmDefaults, TracksUser;

    /** Seeded for every farm: code => name. */
    public const DEFAULTS = [
        'HERD' => 'Own Farm/Herd',
        'COLLECTION' => 'Collection Center',
        'DELIVERY' => 'Sales & Delivery',
        'TRADING' => 'Animal Trading',
        'OFFICE' => 'Office/General',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null ? null : strtoupper(trim($value));
    }

    /**
     * @param  Builder<Section>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
