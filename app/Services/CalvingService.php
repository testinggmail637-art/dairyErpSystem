<?php

namespace App\Services;

use App\Enums\AnimalSource;
use App\Enums\BreedingEventType;
use App\Enums\CalvingOutcome;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Models\Animal;
use App\Models\BreedingEvent;
use App\Models\Calving;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Calvings (spec Phase 5), in one transaction: the mother becomes milking (status history) and her lactation number
 * goes up by one; an alive calf is created as an animal (born on farm, mother set, stage calf, the tag typed by
 * the user, type / breed of the mother, sire from her last service). A calving also ends her pregnancy.
 */
class CalvingService
{
    public function __construct(private readonly AnimalService $animals) {}

    /** @param  array<string, mixed>  $data  validated CalvingRequest */
    public function create(array $data): Calving
    {
        return DB::transaction(function () use ($data) {
            $mother = Animal::query()->lockForUpdate()->findOrFail($data['mother_id']);
            $outcome = CalvingOutcome::from($data['outcome']);

            $calf = $outcome === CalvingOutcome::Alive ? $this->animals->create([
                'tag_no' => $data['calf_tag_no'],
                'name' => $data['calf_name'] ?? null,
                'animal_type' => $mother->animal_type,
                'breed_id' => $mother->breed_id,
                'gender' => $data['calf_gender'],
                'date_of_birth' => $data['calving_date'],
                'dob_is_estimated' => false,
                'source' => AnimalSource::BornOnFarm,
                'mother_id' => $mother->id,
                'sire_info' => $this->lastService($mother->id, $data['calving_date'])?->sire_info,
                'ownership_type' => OwnershipType::OwnHerd,
                'lifecycle_stage' => LifecycleStage::Calf,
            ]) : null;

            $calving = Calving::create([
                'mother_id' => $mother->id, 'calving_date' => $data['calving_date'], 'outcome' => $outcome,
                'calf_gender' => $data['calf_gender'] ?? null, 'calf_id' => $calf?->id, 'notes' => $data['notes'] ?? null,
            ]);

            if ($mother->lifecycle_stage !== LifecycleStage::Milking) {
                $this->animals->change($mother, 'lifecycle_stage', LifecycleStage::Milking->value, $data['calving_date']);
            }
            $mother->increment('lactation_number');

            return $calving;
        });
    }

    /**
     * Undo a calving entered by mistake: the lactation number goes back and the calf is deleted — refused when the
     * calf already has records (then change the calf instead). The mother's stage is left as it is.
     */
    public function delete(Calving $calving): void
    {
        DB::transaction(function () use ($calving) {
            $calf = $calving->calf;
            if ($calf && $calf->transactionCounts() !== []) {
                throw new DomainException(__('Calf :tag already has records — it cannot be removed with this calving.', ['tag' => $calf->tag_no]));
            }

            $calving->delete();
            $calf?->delete();

            $mother = Animal::query()->lockForUpdate()->findOrFail($calving->mother_id);
            if ($mother->lactation_number > 0) {
                $mother->decrement('lactation_number');
            }
        });
    }

    private function lastService(int $animalId, string $before): ?BreedingEvent
    {
        return BreedingEvent::query()->where('animal_id', $animalId)
            ->whereIn('event_type', [BreedingEventType::Ai->value, BreedingEventType::NaturalService->value])
            ->where('event_date', '<=', $before)->latest('event_date')->latest('id')->first();
    }
}
