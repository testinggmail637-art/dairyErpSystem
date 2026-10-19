<?php

namespace App\Models;

use App\Enums\AnimalType;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\AnimalBreedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A cow or buffalo breed (spec Section 6, Phase 2). An animal's breed must be of the animal's type. */
class AnimalBreed extends Model
{
    /** @use HasFactory<AnimalBreedFactory> */
    use BelongsToFarm, HasFactory, TracksUser;

    /** Seeded for every farm (spec Section 6). */
    public const DEFAULTS = [
        'cow' => ['HF', 'Jersey', 'Sahiwal', 'Gir', 'Tharparkar', 'Red Sindhi', 'Crossbred HF', 'Crossbred Jersey', 'Desi'],
        'buffalo' => ['Murrah', 'Nili-Ravi', 'Jaffarabadi', 'Mehsana', 'Bhadawari', 'Surti', 'Desi'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'animal_type',
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'animal_type' => AnimalType::class,
            'is_active' => 'boolean',
        ];
    }

    protected function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * @param  Builder<AnimalBreed>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<AnimalBreed>  $query
     */
    public function scopeOfType(Builder $query, AnimalType|string $type): void
    {
        $query->where('animal_type', $type instanceof AnimalType ? $type->value : $type);
    }

    /** Create any missing default breeds for $farm; existing rows (even renamed or inactive) are left alone. */
    public static function seedDefaultsFor(Farm $farm): void
    {
        foreach (self::DEFAULTS as $type => $names) {
            foreach ($names as $name) {
                static::allFarms()->firstOrCreate(
                    ['farm_id' => $farm->id, 'animal_type' => $type, 'name' => $name],
                    ['is_active' => true],
                );
            }
        }
    }
}
