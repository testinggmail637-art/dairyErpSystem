<?php

namespace App\Models;

use App\Enums\LoanTxnType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A money movement on a loan (spec Phase 3A): EMI / interest payment, prepayment, OD withdrawal / repayment /
 * interest, bank charge. The interest / principal split is a snapshot; the interest or charge part has an expense.
 */
class LoanTransaction extends Model
{
    use Auditable, BelongsToFarm, TracksUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'farm_id',
        'loan_id',
        'txn_date',
        'type',
        'amount',
        'principal_part',
        'interest_part',
        'payment_mode_id',
        'reference_no',
        'expense_id',
        'client_uuid',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'type' => LoanTxnType::class,
            'amount' => 'decimal:2',
            'principal_part' => 'decimal:2',
            'interest_part' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PaymentMode, $this>
     */
    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentMode::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }
}
