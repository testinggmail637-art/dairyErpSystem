<?php

namespace App\Models;

use App\Enums\BreedingEventType;
use App\Enums\PregnancyResult;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Heat, AI, natural service, pregnancy check or abortion of a female animal (spec Phase 5). */
class BreedingEvent extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** Events that start, confirm or end a pregnancy (heat does not). */
    public const PREGNANCY_EVENTS = ['ai', 'natural_service', 'pregnancy_check', 'abortion'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id', 'animal_id', 'event_type', 'event_date', 'sire_info', 'technician', 'result', 'expected_calving_date',
        'cost', 'expense_id', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => BreedingEventType::class,
            'result' => PregnancyResult::class,
            'event_date' => 'date',
            'expected_calving_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
