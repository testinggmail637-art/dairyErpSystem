<?php

namespace App\Models;

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\RateChartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Price of a litre of farmer milk (spec Phase 4): fixed, by fat, by fat + SNF, or a slab table. */
class RateChart extends Model
{
    /** @use HasFactory<RateChartFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id', 'name', 'milk_type', 'method', 'fixed_rate', 'fat_rate', 'snf_rate', 'min_fat', 'min_snf',
        'effective_from', 'effective_to', 'is_default', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milk_type' => MilkType::class,
            'method' => RateMethod::class,
            'fixed_rate' => 'decimal:2',
            'fat_rate' => 'decimal:2',
            'snf_rate' => 'decimal:2',
            'min_fat' => 'decimal:2',
            'min_snf' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RateChartSlab, $this>
     */
    public function slabs(): HasMany
    {
        return $this->hasMany(RateChartSlab::class)->orderBy('fat_from')->orderBy('snf_from');
    }
}
