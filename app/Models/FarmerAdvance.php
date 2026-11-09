<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Database\Factories\FarmerAdvanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money given to a farmer before payment day; deducted from a later payment statement (spec Phase 4). */
class FarmerAdvance extends Model
{
    /** @use HasFactory<FarmerAdvanceFactory> */
    use Auditable, BelongsToFarm, HasFactory, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = ['farm_id', 'farmer_id', 'advance_date', 'amount', 'payment_mode_id', 'reference_no', 'note', 'client_uuid'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => 'decimal:2',
        ];
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
}
