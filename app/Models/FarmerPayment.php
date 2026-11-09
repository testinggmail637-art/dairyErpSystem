<?php

namespace App\Models;

use App\Enums\FarmerPaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One farmer's statement in a payment cycle (spec Phase 4): net = gross + bonus − advance deduction − other deduction.
 * Its collections point at it (farmer_payment_id) and are locked.
 */
class FarmerPayment extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id', 'farmer_payment_cycle_id', 'farmer_id', 'total_qty', 'avg_fat', 'avg_snf', 'gross_amount', 'bonus',
        'advance_deduction', 'other_deduction', 'deduction_note', 'net_payable', 'status', 'paid_on', 'payment_mode_id', 'reference_no',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_qty' => 'decimal:2',
            'avg_fat' => 'decimal:2',
            'avg_snf' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'bonus' => 'decimal:2',
            'advance_deduction' => 'decimal:2',
            'other_deduction' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'status' => FarmerPaymentStatus::class,
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FarmerPaymentCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(FarmerPaymentCycle::class, 'farmer_payment_cycle_id');
    }

    /**
     * @return BelongsTo<Farmer, $this>
     */
    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PaymentMode, $this>
     */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /**
     * @return HasMany<MilkCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(MilkCollection::class);
    }

    public function isPaid(): bool
    {
        return $this->status === FarmerPaymentStatus::Paid;
    }
}
