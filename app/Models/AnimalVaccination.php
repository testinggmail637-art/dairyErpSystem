<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A vaccine given to one animal (spec Phase 5); its cost reaches expenses only through expense_id. */
class AnimalVaccination extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'animal_id', 'vaccine_type_id', 'given_on', 'next_due_on', 'cost', 'expense_id', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['given_on' => 'date', 'next_due_on' => 'date', 'cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class)->withTrashed();
    }

    /**
     * @return BelongsTo<VaccineType, $this>
     */
    public function vaccineType(): BelongsTo
    {
        return $this->belongsTo(VaccineType::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isOverdue(): bool
    {
        return $this->next_due_on !== null && $this->next_due_on->lt(today());
    }
}
