<?php

namespace App\Models;

use App\Enums\AnimalGender;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\AnimalType;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use App\Observers\AnimalObserver;
use App\Support\AnimalPhotoStorage;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cow, buffalo, calf or bull (spec Section 6, Phase 2). Never hard-deleted; soft delete only while it has no
 * transactions. Purchase price is not stored here (it lives in the purchase items, Phase 6).
 */
#[ObservedBy(AnimalObserver::class)]
class Animal extends Model
{
    /** @use HasFactory<AnimalFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /** Date (Y-m-d) for the status-history rows of the next save; null = today. Not a column. */
    public ?string $changeDate = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'tag_no',
        'name',
        'animal_type',
        'breed_id',
        'gender',
        'date_of_birth',
        'dob_is_estimated',
        'source',
        'mother_id',
        'sire_info',
        'ownership_type',
        'lifecycle_stage',
        'status',
        'opening_value',
        'color_marks',
        'photo_path',
        'lactation_number',
        'status_changed_on',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'animal_type' => AnimalType::class,
            'gender' => AnimalGender::class,
            'source' => AnimalSource::class,
            'ownership_type' => OwnershipType::class,
            'lifecycle_stage' => LifecycleStage::class,
            'status' => AnimalStatus::class,
            'date_of_birth' => 'date',
            'status_changed_on' => 'date',
            'dob_is_estimated' => 'boolean',
            'opening_value' => 'decimal:2',
            'lactation_number' => 'integer',
        ];
    }

    protected function setTagNoAttribute(?string $value): void
    {
        $this->attributes['tag_no'] = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * @return BelongsTo<AnimalBreed, $this>
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(AnimalBreed::class, 'breed_id');
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function mother(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'mother_id')->withTrashed();
    }

    /**
     * @return HasMany<Animal, $this>
     */
    public function calves(): HasMany
    {
        return $this->hasMany(Animal::class, 'mother_id');
    }

    /**
     * @return HasMany<AnimalStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AnimalStatusHistory::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'animal_id');
    }

    /**
     * @param  Builder<Animal>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AnimalStatus::Active->value);
    }

    /**
     * Animals that appear in milk entry (spec): female, milking, active.
     *
     * @param  Builder<Animal>  $query
     */
    public function scopeMilking(Builder $query): void
    {
        $query->where('gender', AnimalGender::Female->value)
            ->where('lifecycle_stage', LifecycleStage::Milking->value)
            ->where('status', AnimalStatus::Active->value);
    }

    /**
     * Tag or name contains $term.
     *
     * @param  Builder<Animal>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        $query->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
            ->where('tag_no', 'like', $term.'%')
            ->orWhere('name', 'like', '%'.$term.'%')));
    }

    /** "C-101 · Gauri" or just the tag. */
    public function label(): string
    {
        return $this->name ? "{$this->tag_no} · {$this->name}" : $this->tag_no;
    }

    /** "3 y 4 m", "7 m", "12 d" from the date of birth ("~" when estimated), or null. */
    public function age(): ?string
    {
        if (! $this->date_of_birth) {
            return null;
        }

        $diff = $this->date_of_birth->diff(today());
        $text = match (true) {
            $diff->y > 0 => trim($diff->y.' '.__('y').($diff->m ? ' '.$diff->m.' '.__('m') : '')),
            $diff->m > 0 => $diff->m.' '.__('m'),
            default => $diff->d.' '.__('d'),
        };

        return ($this->dob_is_estimated ? '~' : '').$text;
    }

    public function photoUrl(bool $thumb = false): ?string
    {
        return AnimalPhotoStorage::url($this->photo_path, $thumb);
    }

    /**
     * Records that stop a delete (spec: soft delete only while the animal has no transactions).
     * Later phases add their tables here (milk records, treatments, breeding, purchases, sales).
     *
     * @return array<string, int> label => count, only non-zero
     */
    public function transactionCounts(): array
    {
        return array_filter([
            __('expenses') => $this->expenses()->count(),
            __('calves') => $this->calves()->count(),
        ]);
    }
}
