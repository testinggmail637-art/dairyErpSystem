<?php

namespace App\Services;

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
    public function __construct(private readonly HealthExpenseService $expenses) {}

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
        $animal = $treatment->animal()->first(['id', 'tag_no', 'name']);
        $expense = $this->expenses->sync($treatment, $treatment->expense, [
            'record' => (bool) ($data['record_as_expense'] ?? false), 'cost' => (string) $treatment->cost,
            'date' => $treatment->treatment_date->toDateString(), 'subcategory' => 'Medicine', 'animal_id' => $treatment->animal_id,
            'payment_mode_id' => $data['payment_mode_id'] ?? null, 'payee' => $treatment->vet_name,
            'note' => __('Treatment of :animal: :diagnosis', ['animal' => $animal->label(), 'diagnosis' => $treatment->diagnosis]),
        ]);

        if ($treatment->expense_id !== $expense?->id) {
            $treatment->update(['expense_id' => $expense?->id]);
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
