<?php

namespace App\Models;

use App\Enums\AnimalStatus;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change of an animal's status, lifecycle stage or ownership (spec Section 6, Phase 2). */
class AnimalStatusHistory extends Model
{
    use BelongsToFarm;

    /** Animal columns whose changes are recorded. */
    public const FIELDS = ['status', 'lifecycle_stage', 'ownership_type'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'animal_id',
        'field',
        'old_value',
        'new_value',
        'changed_on',
        'reason',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_on' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function fieldLabel(): string
    {
        return match ($this->field) {
            'status' => __('Status'),
            'lifecycle_stage' => __('Stage'),
            'ownership_type' => __('Ownership'),
            default => $this->field,
        };
    }

    /** Translated label of a stored value ("pregnant_heifer" → "Pregnant heifer"). */
    public function valueLabel(?string $value): string
    {
        if ($value === null) {
            return '—';
        }

        return (match ($this->field) {
            'status' => AnimalStatus::tryFrom($value),
            'lifecycle_stage' => LifecycleStage::tryFrom($value),
            'ownership_type' => OwnershipType::tryFrom($value),
            default => null,
        })?->label() ?? $value;
    }
}
