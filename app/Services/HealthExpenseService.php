<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * The one linked expense of a health record (treatment, vaccination — spec Phase 5): Health → $subcategory, section
 * HERD, the animal, paid (never credit), source = the record, so it can only be changed from there. "Also record as
 * expense" off or cost 0 → no expense (an existing one is deleted). Call inside the caller's transaction.
 */
class HealthExpenseService
{
    /**
     * @param  array{record: bool, cost: string, date: string, subcategory: string, animal_id: int, payment_mode_id: ?int, payee: ?string, note: string}  $e
     */
    public function sync(Model $source, ?Expense $expense, array $e): ?Expense
    {
        if (! $e['record'] || Money::compare($e['cost'], '0') <= 0) {
            $expense?->delete();

            return null;
        }

        $health = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Health')->firstOrFail();
        $expense ??= (new Expense)->forceFill(['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey()]);

        $expense->fill([
            'expense_date' => $e['date'],
            'amount' => Money::round2($e['cost']),
            'category_id' => $health->id,
            'subcategory_id' => $health->children()->where('name', $e['subcategory'])->value('id'),
            'section_id' => Section::query()->where('code', 'HERD')->value('id'),
            'animal_id' => $e['animal_id'],
            'is_credit' => false,
            'payment_mode_id' => $e['payment_mode_id'] ?? PaymentMode::query()->where('code', 'CASH')->value('id'),
            'paid_to_text' => $e['payee'],
            'note' => $e['note'],
        ])->save();

        return $expense;
    }
}
