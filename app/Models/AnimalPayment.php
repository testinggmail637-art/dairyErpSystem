<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A payment to or from an animal trading party, optionally for one purchase / sale (spec Phase 6). */
class AnimalPayment extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = [
        'farm_id', 'party_id', 'direction', 'payment_date', 'amount', 'payment_mode_id', 'animal_purchase_id',
        'animal_sale_id', 'reference_no', 'note', 'client_uuid',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /** @return BelongsTo<PaymentMode, $this> */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /** @return BelongsTo<AnimalPurchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(AnimalPurchase::class, 'animal_purchase_id');
    }

    /** @return BelongsTo<AnimalSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(AnimalSale::class, 'animal_sale_id');
    }
}
