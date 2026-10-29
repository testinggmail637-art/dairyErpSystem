<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use App\Support\CurrentFarm;
use Database\Factories\DeliveryRouteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A milk delivery route (spec Section 6, Phase 3, table `routes`). Named DeliveryRoute so it never clashes with the
 * Route facade. Customers are listed on a route in `route_sequence` order; the delivery user sees only his routes.
 */
class DeliveryRoute extends Model
{
    /** @use HasFactory<DeliveryRouteFactory> */
    use BelongsToFarm, HasFactory, TracksUser;

    protected $table = 'routes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'name',
        'delivery_user_id',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliveryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_user_id');
    }

    /**
     * Who can be put on a route: active users of the current farm allowed to update deliveries.
     *
     * @return array<int, string> id => name
     */
    public static function deliveryUserOptions(): array
    {
        return User::query()->active()
            ->whereHas('farms', fn ($q) => $q->whereKey(app(CurrentFarm::class)->id()))
            ->permission('deliveries.update')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  Builder<DeliveryRoute>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<DeliveryRoute>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
