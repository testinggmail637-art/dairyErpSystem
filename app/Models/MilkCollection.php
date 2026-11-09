<?php

namespace App\Models;

use App\Contracts\MovesMilkStock;
use App\Enums\MilkMovementType;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Enums\QualityStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\MilkCollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Milk bought from a farmer in one shift (spec Phase 4). Rate and amount are copied from the rate chart when saved.
 * Accepted milk goes into the collection stock of its milk type; rejected milk has amount 0 and no stock.
 */
class MilkCollection extends Model implements MovesMilkStock
{
    /** @use HasFactory<MilkCollectionFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id', 'collection_date', 'shift', 'farmer_id', 'milk_type', 'quantity_litre', 'fat', 'snf', 'clr',
        'rate_chart_id', 'rate_per_litre', 'amount', 'quality_status', 'rejection_reason', 'farmer_payment_id',
        'client_uuid', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_date' => 'date',
            'shift' => MilkShift::class,
            'milk_type' => MilkType::class,
            'quality_status' => QualityStatus::class,
            'quantity_litre' => 'decimal:2',
            'fat' => 'decimal:2',
            'snf' => 'decimal:2',
            'clr' => 'decimal:2',
            'rate_per_litre' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RateChart, $this>
     */
    public function rateChart(): BelongsTo
    {
        return $this->belongsTo(RateChart::class)->withTrashed();
    }

    public function isAccepted(): bool
    {
        return $this->quality_status === QualityStatus::Accepted;
    }

    public function milkStockTarget(): array
    {
        return [
            'source' => MilkSource::Collection,
            'milk_type' => $this->milk_type,
            'date' => $this->collection_date->toDateString(),
            'shift' => $this->shift,
            'type' => MilkMovementType::Collection,
        ];
    }

    public function milkStockQuantity(): string
    {
        return $this->isAccepted() ? (string) $this->quantity_litre : '0';
    }
}
