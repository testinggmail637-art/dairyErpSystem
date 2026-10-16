<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expense category (parent_id null) or subcategory (parent_id = its category). Only two levels.
 */
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use BelongsToFarm, HasFactory, TracksUser;

    /** Seeded for every farm (spec Section 6, Phase 1): category => subcategories. */
    public const DEFAULTS = [
        'Feed' => ['Green Fodder', 'Dry Fodder (Bhusa)', 'Cattle Feed', 'Mineral Mixture', 'Silage'],
        'Health' => ['Medicine', 'Veterinary Fees', 'Vaccination', 'AI / Breeding'],
        'Labour' => ['Salary', 'Daily Wages'],
        'Utilities' => ['Electricity', 'Water', 'Diesel/Fuel'],
        'Transport' => ['Milk Transport', 'Animal Transport', 'Vehicle Maintenance'],
        'Maintenance' => ['Shed Repair', 'Equipment Repair'],
        'Collection Center' => ['Testing Chemicals', 'Cans & Equipment'],
        'Office' => ['Stationery', 'Phone/Internet', 'Rent'],
        'Miscellaneous' => ['Other'],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'parent_id',
        'name',
        'code',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    /** Full label for dropdowns and reports: "Feed › Cattle Feed". */
    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * @param  Builder<ExpenseCategory>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<ExpenseCategory>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ExpenseCategory>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** Create any missing default categories/subcategories for $farm; existing rows (renamed/inactive) are kept. */
    public static function seedDefaultsFor(Farm $farm): void
    {
        $position = 0;

        foreach (self::DEFAULTS as $categoryName => $subcategories) {
            $category = static::allFarms()->firstOrCreate(
                ['farm_id' => $farm->id, 'parent_id' => null, 'name' => $categoryName],
                ['is_active' => true, 'sort_order' => ($position += 10)],
            );

            foreach ($subcategories as $i => $subName) {
                static::allFarms()->firstOrCreate(
                    ['farm_id' => $farm->id, 'parent_id' => $category->id, 'name' => $subName],
                    ['is_active' => true, 'sort_order' => ($i + 1) * 10],
                );
            }
        }
    }
}
