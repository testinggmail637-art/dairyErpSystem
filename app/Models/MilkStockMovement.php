<?php

namespace App\Models;

use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of the milk ledger (spec Section 6, Phase 2): litres in or out of one source × milk type stock.
 * Exactly one of quantity_in / quantity_out is above zero. Written only by App\Services\MilkStockService.
 */
class MilkStockMovement extends Model
{
    use BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'movement_date',
        'shift',
        'stock_source',
        'milk_type',
        'movement_type',
        'quantity_in',
        'quantity_out',
        'reference_type',
        'reference_id',
        'reason',
        'client_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'shift' => MilkShift::class,
            'stock_source' => MilkSource::class,
            'milk_type' => MilkType::class,
            'movement_type' => MilkMovementType::class,
            'quantity_in' => 'decimal:2',
            'quantity_out' => 'decimal:2',
        ];
    }

    /**
     * The record that caused it (production entry, collection, delivery); null for usage and adjustments.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
