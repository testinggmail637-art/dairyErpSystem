<?php

namespace App\Services;

use App\Enums\AnimalStatus;
use App\Models\Animal;
use App\Support\AnimalPhotoStorage;
use App\Support\CurrentFarm;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Animals (spec Section 6, Phase 2): add, edit, soft delete, list filters and stage counts.
 * The photo file is written before the transaction and removed again if saving fails.
 */
class AnimalService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?UploadedFile $photo = null): Animal
    {
        $path = $photo ? AnimalPhotoStorage::store($photo, $this->currentFarm->id()) : null;

        try {
            return DB::transaction(fn () => Animal::create($data + [
                'photo_path' => $path,
                'status' => AnimalStatus::Active,
            ]));
        } catch (\Throwable $e) {
            AnimalPhotoStorage::delete($path);

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(Animal $animal, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Animal
    {
        $oldPath = $animal->photo_path;
        $newPath = $photo ? AnimalPhotoStorage::store($photo, $animal->farm_id) : null;

        if ($newPath || $removePhoto) {
            $data['photo_path'] = $newPath;
        }

        try {
            DB::transaction(fn () => $animal->update($data));
        } catch (\Throwable $e) {
            AnimalPhotoStorage::delete($newPath);

            throw $e;
        }

        if (($newPath || $removePhoto) && $oldPath) {
            AnimalPhotoStorage::delete($oldPath);
        }

        return $animal;
    }

    /**
     * Status / stage / ownership action (Task 2.3): one field, the date it happened and (from the request) a reason.
     * AnimalObserver writes the history row in the same transaction; a status change also sets status_changed_on.
     */
    public function change(Animal $animal, string $field, string $value, string $changedOn): Animal
    {
        DB::transaction(function () use ($animal, $field, $value, $changedOn) {
            $animal->changeDate = $changedOn;
            $animal->setAttribute($field, $value);

            if ($field === 'status') {
                $animal->status_changed_on = $changedOn;
            }

            $animal->save();
            $animal->changeDate = null;
        });

        return $animal;
    }

    /** Soft delete — refused while the animal has transactions (spec). The photo stays with the deleted record. */
    public function delete(Animal $animal): void
    {
        $counts = $animal->transactionCounts();

        if ($counts !== []) {
            throw new DomainException(__('This animal cannot be deleted because it has :items. Change its status instead (sold, deceased …).', [
                'items' => collect($counts)->map(fn ($n, $label) => "{$n} {$label}")->implode(', '),
            ]));
        }

        $animal->delete();
    }

    /**
     * List filters (all optional): type, stage, status ('' = active, 'all' = any), ownership, gender, breed_id, search.
     *
     * @param  Builder<Animal>  $query
     * @param  array<string, mixed>  $f
     * @return Builder<Animal>
     */
    public function applyFilters(Builder $query, array $f): Builder
    {
        $status = $f['status'] ?? '';

        return $query
            ->when($status === '', fn ($q) => $q->where('status', AnimalStatus::Active->value))
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('animal_type', $v))
            ->when($f['stage'] ?? null, fn ($q, $v) => $q->where('lifecycle_stage', $v))
            ->when($f['ownership'] ?? null, fn ($q, $v) => $q->where('ownership_type', $v))
            ->when($f['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($f['breed_id'] ?? null, fn ($q, $v) => $q->where('breed_id', $v))
            ->search($f['search'] ?? null);
    }

    /**
     * Active animals per type and stage, one GROUP BY query: ['cow' => ['milking' => 12, …], 'buffalo' => […]].
     *
     * @return array<string, array<string, int>>
     */
    public function stageCounts(): array
    {
        $counts = [];
        Animal::query()->toBase()
            ->where('status', AnimalStatus::Active->value)
            ->whereNull('deleted_at')
            ->selectRaw('animal_type, lifecycle_stage, COUNT(*) AS cnt')
            ->groupBy('animal_type', 'lifecycle_stage')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[$row->animal_type][$row->lifecycle_stage] = (int) $row->cnt;
            });

        return $counts;
    }
}
