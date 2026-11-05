<?php

namespace App\Models;

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's daily order of one product in one shift (spec Section 6, Phase 3). The delivery sheet makes a
 * pending delivery for every active subscription valid on the date and not paused.
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'customer_id',
        'product_id',
        'shift',
        'quantity',
        'stock_source',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift' => MilkShift::class,
            'quantity' => 'decimal:2',
            'stock_source' => MilkSource::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SubscriptionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return HasMany<SubscriptionPause, $this>
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(SubscriptionPause::class);
    }

    /**
     * Adds `paused_today` (bool) as one EXISTS sub-select.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeWithPausedOn(Builder $query, string $date): void
    {
        $query->withExists(['pauses as paused_today' => fn ($q) => $q->where('from_date', '<=', $date)->where('to_date', '>=', $date)]);
    }
}
