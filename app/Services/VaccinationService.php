<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalVaccination;
use App\Models\VaccineType;
use App\Support\Money;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Vaccinations (spec Phase 5). One row per animal — single entry is a bulk entry of one animal. next_due_on defaults
 * to given_on + the vaccine's interval_days. With "Also record as expense" and a cost > 0, each row keeps its own
 * expense (Health → Vaccination, the animal), like treatments, so the cost is counted once and stays per animal.
 */
class VaccinationService
{
    public function __construct(private readonly HealthExpenseService $expenses) {}

    /**
     * @param  array<string, mixed>  $data  validated VaccinationRequest (animal_ids, vaccine_type_id, given_on, …)
     * @return int rows saved
     */
    public function createBulk(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $type = VaccineType::query()->findOrFail($data['vaccine_type_id']);
            $animals = Animal::query()->whereKey($data['animal_ids'])->get(['id', 'tag_no', 'name'])->keyBy('id');

            foreach ($data['animal_ids'] as $animalId) {
                $row = AnimalVaccination::create($this->fields($data, $type) + ['animal_id' => $animalId]);
                $this->syncExpense($row, $data, $type, $animals[$animalId]);
            }

            return count($data['animal_ids']);
        });
    }

    /** @param  array<string, mixed>  $data  validated VaccinationRequest with one animal_id */
    public function update(AnimalVaccination $row, array $data): AnimalVaccination
    {
        return DB::transaction(function () use ($row, $data) {
            $type = VaccineType::query()->findOrFail($data['vaccine_type_id']);
            $row->update($this->fields($data, $type) + ['animal_id' => $data['animal_ids'][0]]);
            $this->syncExpense($row, $data, $type, $row->animal()->first(['id', 'tag_no', 'name']));

            return $row;
        });
    }

    public function delete(AnimalVaccination $row): void
    {
        DB::transaction(function () use ($row) {
            $expense = $row->expense;
            $row->delete();
            $expense?->delete();
        });
    }

    /** A vaccine with vaccinations can only be made inactive. */
    public function deleteType(VaccineType $type): void
    {
        if ($type->vaccinations()->exists()) {
            throw new DomainException(__(':name has vaccinations — mark it inactive instead.', ['name' => $type->name]));
        }
        $type->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data, VaccineType $type): array
    {
        $nextDue = $data['next_due_on'] ?? ($type->interval_days
            ? Carbon::parse($data['given_on'])->addDays($type->interval_days)->toDateString() : null);

        return [
            'vaccine_type_id' => $type->id, 'given_on' => $data['given_on'], 'next_due_on' => $nextDue,
            'cost' => Money::round2((string) ($data['cost'] ?? '0')), 'notes' => $data['notes'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function syncExpense(AnimalVaccination $row, array $data, VaccineType $type, Animal $animal): void
    {
        $expense = $this->expenses->sync($row, $row->expense, [
            'record' => (bool) ($data['record_as_expense'] ?? false), 'cost' => (string) $row->cost,
            'date' => $row->given_on->toDateString(), 'subcategory' => 'Vaccination', 'animal_id' => $row->animal_id,
            'payment_mode_id' => $data['payment_mode_id'] ?? null, 'payee' => null,
            'note' => __(':vaccine vaccination of :animal', ['vaccine' => $type->name, 'animal' => $animal->label()]),
        ]);

        if ($row->expense_id !== $expense?->id) {
            $row->update(['expense_id' => $expense?->id]);
        }
    }
}
