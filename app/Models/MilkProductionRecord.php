<?php

namespace App\Models;

use App\Contracts\MovesMilkStock;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
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
 * Moves milk into the own-farm stock of the animal's type (MilkStockService).
 */
class MilkProductionRecord extends Model implements MovesMilkStock
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

    public function milkStockTarget(): array
    {
        // the loaded animal is stale when the entry was moved to another animal
        $animal = $this->relationLoaded('animal') && $this->animal?->id === $this->animal_id
            ? $this->animal
            : $this->load('animal')->animal;

        return [
            'source' => MilkSource::OwnFarm,
            'milk_type' => MilkType::from($animal->animal_type->value),
            'date' => $this->production_date->toDateString(),
            'shift' => $this->shift,
            'type' => MilkMovementType::Production,
        ];
    }

    public function milkStockQuantity(): string
    {
        return (string) $this->quantity_litre;
    }
}
