<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-created Finance expenses (spec Phase 3A): loan interest and bank charges / processing fees. Each one belongs to
 * its source record (expenses.source morph), is paid (never credit), sits in the Office / General section, and can
 * only be changed through its source (ExpenseService refuses to edit auto-created expenses).
 */
class FinanceExpenseService
{
    public const INTEREST = 'Loan Interest';

    public const CHARGES = 'Bank Charges';

    /** Create, update or (amount 0 / null) remove the expense of $source. Call inside the caller's transaction. */
    public function sync(Model $source, ?string $amount, string $date, string $subcategory, ?int $paymentModeId, string $payee, string $note, ?int $loanId = null): ?Expense
    {
        $expense = Expense::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->first();

        if ($amount === null || Money::compare($amount, '0') <= 0) {
            $expense?->delete();

            return null;
        }

        $finance = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Finance')->firstOrFail();

        $expense ??= (new Expense)->forceFill(['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey()]);
        $expense->fill([
            'expense_date' => $date,
            'amount' => Money::round2($amount),
            'category_id' => $finance->id,
            'subcategory_id' => $finance->children()->where('name', $subcategory)->value('id'),
            'section_id' => Section::query()->where('code', 'OFFICE')->value('id') ?? Section::query()->orderBy('id')->value('id'),
            'is_credit' => false,
            'payment_mode_id' => $paymentModeId ?? $this->bankMode(),
            'paid_to_text' => $payee,
            'note' => $note,
            'loan_id' => $loanId, // Expenses list "Loan" filter and loan reports (DECISIONS #153)
        ])->save();

        return $expense;
    }

    /** Payment mode for bank-side debits (processing fee, OD interest): "Bank Transfer", else the first mode. */
    public function bankMode(): ?int
    {
        return PaymentMode::query()->where('code', 'BANK')->value('id') ?? PaymentMode::query()->orderBy('id')->value('id');
    }
}
