<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money received from a customer (spec Section 6, Phase 3); set against bills FIFO by PaymentAllocationService. */
class CustomerPayment extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'customer_id',
        'payment_date',
        'amount',
        'payment_mode_id',
        'reference_no',
        'collected_by',
        'client_uuid',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
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
     * @return BelongsTo<PaymentMode, $this>
     */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
