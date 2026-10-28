<?php

namespace App\Models;

use App\Enums\MilkShift;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\MilkProductionRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Litres one animal gave in one shift (spec Section 6, Phase 2). One per animal, date and shift.
 * The milk type (cow / buffalo) is the animal's type. Audit-logged; no soft delete.
 */
class MilkProductionRecord extends Model
{
    /** @use HasFactory<MilkProductionRecordFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /** Highest litres accepted for one animal in one shift (spec). */
    public const MAX_LITRES = '60';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'production_date',
        'shift',
        'animal_id',
        'quantity_litre',
        'fat',
        'snf',
        'notes',
        'client_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'shift' => MilkShift::class,
            'quantity_litre' => 'decimal:2',
            'fat' => 'decimal:2',
            'snf' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }
}
