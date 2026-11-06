<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part of a customer payment set against a finalized bill, oldest bill first (spec Section 6). */
class PaymentAllocation extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'customer_payment_id',
        'bill_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
