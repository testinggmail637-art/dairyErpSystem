<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Interest rate change or prepayment re-calculation of a loan (spec Phase 3A): old → new rate and EMI, with reason. */
class LoanRevision extends Model
{
    use BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'loan_id',
        'effective_date',
        'old_rate',
        'new_rate',
        'old_emi',
        'new_emi',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'old_rate' => 'decimal:2',
            'new_rate' => 'decimal:2',
            'old_emi' => 'decimal:2',
            'new_emi' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withTrashed();
    }
}
