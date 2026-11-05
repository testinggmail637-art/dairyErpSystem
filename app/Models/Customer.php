<?php

namespace App\Models;

use App\Enums\B2bCategory;
use App\Enums\CustomerType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A milk buyer (spec Section 6, Phase 3): home delivery (B2C) or bulk (B2B). Own rates per product in
 * customer_rates; PricingService falls back to the product's default rate.
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, BelongsToFarm, HasFactory, SoftDeletes, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'code',
        'name',
        'customer_type',
        'b2b_category',
        'phone',
        'alt_phone',
        'address',
        'area',
        'route_id',
        'route_sequence',
        'gstin',
        'opening_balance',
        'credit_limit',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_type' => CustomerType::class,
            'b2b_category' => B2bCategory::class,
            'route_sequence' => 'integer',
            'opening_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<DeliveryRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    /**
     * @return HasMany<CustomerRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(CustomerRate::class);
    }

    protected function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = User::normalizePhone($value);
    }

    protected function setAltPhoneAttribute(?string $value): void
    {
        $this->attributes['alt_phone'] = User::normalizePhone($value);
    }

    /**
     * @param  Builder<Customer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Name, exact code or phone.
     *
     * @param  Builder<Customer>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($term, $digits) {
            $q->where('name', 'like', "%{$term}%")->orWhere('code', strtoupper($term));

            if (strlen($digits) >= 3) {
                $like = '%'.(User::normalizePhone($digits) ?: $digits).'%';
                $q->orWhere('phone', 'like', $like)->orWhere('alt_phone', 'like', $like);
            }
        });
    }
}
