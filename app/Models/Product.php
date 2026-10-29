<?php

namespace App\Models;

use App\Enums\MilkType;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A product sold to customers (spec Section 6, Phase 3) — only raw cow / buffalo milk per litre in v1.
 * `default_rate` is used when a customer has no own rate; deliveries and bills copy the rate, so changing it
 * never changes history.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /** Seeded for every farm (spec Section 6): code => [name, milk type, ₹ per litre]. */
    public const DEFAULTS = [
        'COW' => ['Cow Milk', MilkType::Cow, '60.00'],
        'BUF' => ['Buffalo Milk', MilkType::Buffalo, '70.00'],
    ];

    public const UNIT = 'litre';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'milk_type',
        'default_rate',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milk_type' => MilkType::class,
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    protected function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null ? null : strtoupper(trim($value));
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** Create any missing default products for $farm (by code); existing rows (even changed or inactive) are left alone. */
    public static function seedDefaultsFor(Farm $farm): void
    {
        foreach (self::DEFAULTS as $code => [$name, $milkType, $rate]) {
            static::allFarms()->withTrashed()->firstOrCreate(
                ['farm_id' => $farm->id, 'code' => $code],
                ['name' => $name, 'milk_type' => $milkType, 'default_rate' => $rate, 'is_active' => true],
            );
        }
    }
}
