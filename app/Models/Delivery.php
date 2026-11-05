<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer × product × date × shift delivery (spec Section 6, Phase 3). Rate is a snapshot from
 * PricingService; a row with bill_id is locked.
 */
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'delivery_date',
        'shift',
        'customer_id',
        'product_id',
        'route_id',
        'subscription_id',
        'stock_source',
        'planned_qty',
        'delivered_qty',
        'rate',
        'amount',
        'status',
        'skip_reason',
        'delivered_by',
        'delivered_at',
        'fat',
        'snf',
        'buyer_measured_qty',
        'buyer_fat',
        'buyer_snf',
        'bill_id',
        'client_uuid',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'shift' => MilkShift::class,
            'stock_source' => MilkSource::class,
            'planned_qty' => 'decimal:2',
            'delivered_qty' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
            'fat' => 'decimal:2',
            'snf' => 'decimal:2',
            'buyer_measured_qty' => 'decimal:2',
            'buyer_fat' => 'decimal:2',
            'buyer_snf' => 'decimal:2',
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
     * @return BelongsTo<DeliveryRoute, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
