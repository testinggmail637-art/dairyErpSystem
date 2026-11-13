<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\TreatmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A treatment given to an animal (spec Phase 5); its cost reaches expenses only through expense_id. */
class Treatment extends Model
{
    /** @use HasFactory<TreatmentFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id', 'animal_id', 'treatment_date', 'diagnosis', 'medicine', 'dose', 'vet_name', 'cost',
        'milk_withdrawal_until', 'expense_id', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'treatment_date' => 'date',
            'milk_withdrawal_until' => 'date',
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

    /** Milk of this animal should not be sold until (and including) milk_withdrawal_until. */
    public function isUnderWithdrawal(): bool
    {
        return $this->milk_withdrawal_until !== null && $this->milk_withdrawal_until->gte(today());
    }
}
