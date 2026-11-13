<?php

namespace App\Services;

use App\Enums\AnimalType;
use App\Enums\BreedingEventType;
use App\Enums\LifecycleStage;
use App\Enums\PregnancyResult;
use App\Models\Animal;
use App\Models\BreedingEvent;
use App\Support\CurrentFarm;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Breeding events (spec Phase 5):
 * - AI / natural service → expected calving = event date + gestation days of the animal type (farm settings);
 * - pregnancy check positive → expected calving copied from the last service before it; a heifer becomes a
 *   pregnant heifer (a milking cow stays milking — "pregnant" is read from her latest event);
 * - negative check or abortion → a pregnant heifer goes back to heifer;
 * - cost with "Also record as expense" → one Health → AI / Breeding expense (HealthExpenseService).
 * An animal is pregnant while her latest pregnancy event (service, check, abortion) is a positive check.
 */
class BreedingService
{
    public function __construct(
        private readonly HealthExpenseService $expenses,
        private readonly AnimalService $animals,
        private readonly CurrentFarm $currentFarm,
    ) {}

    /** @param  array<string, mixed>  $data  validated BreedingEventRequest */
    public function create(array $data): BreedingEvent
    {
        return DB::transaction(function () use ($data) {
            $event = BreedingEvent::create($this->fields($data));
            $this->afterSave($event, $data);

            return $event;
        });
    }

    /** @param  array<string, mixed>  $data  validated BreedingEventRequest */
    public function update(BreedingEvent $event, array $data): BreedingEvent
    {
        return DB::transaction(function () use ($event, $data) {
            $event->update($this->fields($data));
            $this->afterSave($event, $data);

            return $event;
        });
    }

    /** The animal's stage is not changed back — use the stage action on the animal page if needed. */
    public function delete(BreedingEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $expense = $event->expense;
            $event->delete();
            $expense?->delete();
        });
    }

    public function isPregnant(Animal $animal): bool
    {
        $latest = $this->latestPregnancyEvents()->where('e.animal_id', $animal->id)->first(['e.event_type', 'e.result']);

        return $latest?->event_type === BreedingEventType::PregnancyCheck->value && $latest->result === PregnancyResult::Positive->value;
    }

    /**
     * Expected calvings between two dates: animals whose latest pregnancy event (a service, or a positive check) carries
     * an expected calving date in the range. One row per animal: {animal_id, event_type, expected_calving_date}.
     *
     * @return Builder
     */
    public function expectedCalvings(string $from, string $to)
    {
        return $this->latestPregnancyEvents()->whereBetween('e.expected_calving_date', [$from, $to])
            ->select(['e.id', 'e.animal_id', 'e.event_type', 'e.expected_calving_date'])->orderBy('e.expected_calving_date');
    }

    /** Each animal's latest service / check / abortion of this farm (no later one exists). */
    private function latestPregnancyEvents(): Builder
    {
        return DB::table('breeding_events as e')
            ->where('e.farm_id', $this->currentFarm->id())
            ->whereIn('e.event_type', BreedingEvent::PREGNANCY_EVENTS)
            ->whereNotExists(fn ($later) => $later->from('breeding_events as l')
                ->whereColumn('l.animal_id', 'e.animal_id')->whereIn('l.event_type', BreedingEvent::PREGNANCY_EVENTS)
                ->where(fn ($q) => $q->whereColumn('l.event_date', '>', 'e.event_date')
                    ->orWhere(fn ($q) => $q->whereColumn('l.event_date', 'e.event_date')->whereColumn('l.id', '>', 'e.id'))));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $type = BreedingEventType::from($data['event_type']);
        $isCheck = $type === BreedingEventType::PregnancyCheck;
        $positive = $isCheck && ($data['result'] ?? null) === PregnancyResult::Positive->value;

        return [
            'animal_id' => $data['animal_id'],
            'event_type' => $type,
            'event_date' => $data['event_date'],
            'sire_info' => $type->isService() ? ($data['sire_info'] ?? null) : null,
            'technician' => $data['technician'] ?? null,
            'result' => $isCheck ? $data['result'] : null,
            'expected_calving_date' => match (true) {
                $type->isService() => $this->dueDate((int) $data['animal_id'], $data['event_date']),
                $positive => $this->lastService((int) $data['animal_id'], $data['event_date'])?->expected_calving_date,
                default => null,
            },
            'cost' => $data['cost'] ?? '0.00',
            'notes' => $data['notes'] ?? null,
        ];
    }

    private function dueDate(int $animalId, string $date): string
    {
        $type = Animal::query()->whereKey($animalId)->value('animal_type');
        $days = (int) $this->currentFarm->farm()->setting($type === AnimalType::Buffalo ? 'buffalo_gestation_days' : 'cow_gestation_days');

        return Carbon::parse($date)->addDays($days)->toDateString();
    }

    private function lastService(int $animalId, string $before): ?BreedingEvent
    {
        return BreedingEvent::query()->where('animal_id', $animalId)
            ->whereIn('event_type', [BreedingEventType::Ai->value, BreedingEventType::NaturalService->value])
            ->where('event_date', '<=', $before)->latest('event_date')->latest('id')->first();
    }

    /** Stage change from a check / abortion, and the linked expense. @param  array<string, mixed>  $data */
    private function afterSave(BreedingEvent $event, array $data): void
    {
        $animal = Animal::query()->findOrFail($event->animal_id);
        $positive = $event->result === PregnancyResult::Positive;
        $endsPregnancy = $event->event_type === BreedingEventType::Abortion
            || ($event->event_type === BreedingEventType::PregnancyCheck && ! $positive);

        if ($positive && $animal->lifecycle_stage === LifecycleStage::Heifer) {
            $this->animals->change($animal, 'lifecycle_stage', LifecycleStage::PregnantHeifer->value, $event->event_date->toDateString());
        } elseif ($endsPregnancy && $animal->lifecycle_stage === LifecycleStage::PregnantHeifer) {
            $this->animals->change($animal, 'lifecycle_stage', LifecycleStage::Heifer->value, $event->event_date->toDateString());
        }

        $expense = $this->expenses->sync($event, $event->expense, [
            'record' => (bool) ($data['record_as_expense'] ?? false), 'cost' => (string) $event->cost,
            'date' => $event->event_date->toDateString(), 'subcategory' => 'AI / Breeding', 'animal_id' => $event->animal_id,
            'payment_mode_id' => $data['payment_mode_id'] ?? null, 'payee' => $event->technician,
            'note' => __(':event of :animal', ['event' => $event->event_type->label(), 'animal' => $animal->label()]),
        ]);

        if ($event->expense_id !== $expense?->id) {
            $event->update(['expense_id' => $expense?->id]);
        }
    }
}
