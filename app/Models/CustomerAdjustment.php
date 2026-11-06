<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A signed correction to what a customer owes (spec Section 6): + adds (e.g. extra charge), − reduces (discount,
 * correction). Counts in the ledger balance and in the next bill's "adjustments in period".
 */
class CustomerAdjustment extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'customer_id',
        'adjustment_date',
        'amount',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
