<?php

namespace App\Models;

use App\Enums\AnimalGender;
use App\Enums\CalvingOutcome;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A calving of a cow / buffalo (spec Phase 5): starts a new lactation; an alive calf becomes an animal. */
class Calving extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'mother_id', 'calving_date', 'outcome', 'calf_gender', 'calf_id', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['calving_date' => 'date', 'outcome' => CalvingOutcome::class, 'calf_gender' => AnimalGender::class];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'mother_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function calf(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'calf_id')->withTrashed();
    }
}
