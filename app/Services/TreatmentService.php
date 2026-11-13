<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\Treatment;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Treatments (spec Phase 5). "Also record as expense" with a cost > 0 keeps exactly one linked expense
 * (Health → Medicine, section HERD, the animal, source = the treatment): created with the treatment, updated when
 * the cost / date / mode change, removed when unticked, the cost becomes 0 or the treatment is deleted.
 * Unticked = the cost is information only. This is the only way treatment costs reach expenses.
 */
class TreatmentService
{
    /** @param  array<string, mixed>  $data  validated TreatmentRequest */
    public function create(array $data): Treatment
    {
        return DB::transaction(function () use ($data) {
            $treatment = Treatment::create($this->fields($data));
            $this->syncExpense($treatment, $data);

            return $treatment;
        });
    }

    /** @param  array<string, mixed>  $data  validated TreatmentRequest */
    public function update(Treatment $treatment, array $data): Treatment
    {
        return DB::transaction(function () use ($treatment, $data) {
            $treatment->update($this->fields($data));
            $this->syncExpense($treatment, $data);

            return $treatment;
        });
    }

    public function delete(Treatment $treatment): void
    {
        DB::transaction(function () use ($treatment) {
            $expense = $treatment->expense;
            $treatment->delete();
            $expense?->delete();
        });
    }

    /** Payment mode of the linked expense, for the edit form. */
    public function paymentModeId(Treatment $treatment): ?int
    {
        return $treatment->expense?->payment_mode_id;
    }

    /** @param  array<string, mixed>  $data */
    private function syncExpense(Treatment $treatment, array $data): void
    {
        $expense = $treatment->expense;

        if (! ($data['record_as_expense'] ?? false) || Money::compare((string) $treatment->cost, '0') <= 0) {
            if ($expense) {
                $treatment->update(['expense_id' => null]);
                $expense->delete();
            }

            return;
        }

        $health = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Health')->firstOrFail();
        $expense ??= (new Expense)->forceFill(['source_type' => $treatment->getMorphClass(), 'source_id' => $treatment->getKey()]);
        $animal = $treatment->animal()->first(['id', 'tag_no', 'name']);

        $expense->fill([
            'expense_date' => $treatment->treatment_date->toDateString(),
            'amount' => $treatment->cost,
            'category_id' => $health->id,
            'subcategory_id' => $health->children()->where('name', 'Medicine')->value('id'),
            'section_id' => Section::query()->where('code', 'HERD')->value('id'),
            'animal_id' => $treatment->animal_id,
            'is_credit' => false,
            'payment_mode_id' => $data['payment_mode_id'] ?? PaymentMode::query()->where('code', 'CASH')->value('id'),
            'paid_to_text' => $treatment->vet_name,
            'note' => __('Treatment of :animal: :diagnosis', ['animal' => $animal->label(), 'diagnosis' => $treatment->diagnosis]),
        ])->save();

        if ($treatment->expense_id !== $expense->id) {
            $treatment->update(['expense_id' => $expense->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return collect($data)->only(['animal_id', 'treatment_date', 'diagnosis', 'medicine', 'dose', 'vet_name', 'milk_withdrawal_until', 'notes'])
            ->put('cost', Money::round2((string) ($data['cost'] ?? '0')))
            ->all();
    }
}
