<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An advance paid to a staff member, recovered from later salary (spec Phase 6). */
class StaffAdvance extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = ['farm_id', 'staff_member_id', 'advance_date', 'amount', 'payment_mode_id', 'note', 'client_uuid'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'advance_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** @return BelongsTo<PaymentMode, $this> */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }
}
