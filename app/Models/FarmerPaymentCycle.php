<?php

namespace App\Models;

use App\Enums\CycleStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A farmer payment period (spec Phase 4) with one statement per farmer; cycles never overlap. */
class FarmerPaymentCycle extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'cycle_no', 'from_date', 'to_date', 'status', 'finalized_at', 'finalized_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'status' => CycleStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FarmerPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(FarmerPayment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === CycleStatus::Draft;
    }
}
