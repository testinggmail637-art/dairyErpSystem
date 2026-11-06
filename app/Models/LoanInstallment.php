<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFarm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of a loan's EMI / interest schedule (spec Phase 3A). Paid status is computed from the transactions. */
class LoanInstallment extends Model
{
    use BelongsToFarm;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'loan_id',
        'installment_no',
        'due_date',
        'opening_principal',
        'emi_amount',
        'principal_part',
        'interest_part',
        'closing_principal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installment_no' => 'integer',
            'due_date' => 'date',
            'opening_principal' => 'decimal:2',
            'emi_amount' => 'decimal:2',
            'principal_part' => 'decimal:2',
            'interest_part' => 'decimal:2',
            'closing_principal' => 'decimal:2',
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
