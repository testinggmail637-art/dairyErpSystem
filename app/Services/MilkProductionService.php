<?php

namespace App\Services;

use App\Enums\MilkShift;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Milk production entry (spec 7.3): the date + shift grid, single entries, and the numbers behind them.
 * Every write runs in a transaction so that the milk stock (Task 2.6) can be moved in the same one.
 */
class MilkProductionService
{
    /** How far back the "last 3 entries" hint looks. */
    private const HINT_DAYS = 60;

    /**
     * Animals of the grid: every active milking female (spec), plus any animal that already has an entry for
     * this date and shift (so a saved entry stays editable after the animal was dried off or sold).
     *
     * @return Collection<int, Animal>
     */
    public function gridAnimals(string $date, MilkShift $shift): Collection
    {
        $withEntry = MilkProductionRecord::query()->select('animal_id')
            ->where('production_date', $date)->where('shift', $shift->value);

        return Animal::query()
            ->select(['id', 'tag_no', 'name', 'animal_type', 'breed_id', 'gender', 'lifecycle_stage', 'status'])
            ->with('breed:id,name')
            ->where(fn ($q) => $q->milking()->orWhereIn('id', $withEntry))
            ->orderBy('animal_type')
            ->orderBy('tag_no')
            ->get();
    }

    /**
     * Saved entries of one date and shift, keyed by animal id.
     *
     * @param  list<int>|null  $animalIds
     * @return Collection<int, MilkProductionRecord>
     */
    public function entriesFor(string $date, MilkShift $shift, ?array $animalIds = null): Collection
    {
        return MilkProductionRecord::query()
            ->where('production_date', $date)
            ->where('shift', $shift->value)
            ->when($animalIds !== null, fn ($q) => $q->whereIn('animal_id', $animalIds))
            ->get()
            ->keyBy('animal_id');
    }

    /**
     * The last 3 entries of each animal for this shift before $date (grid hint), one query with ROW_NUMBER().
     *
     * @param  list<int>  $animalIds
     * @return array<int, list<array{date: string, litres: string}>> animal id => newest first
     */
    public function lastEntries(array $animalIds, MilkShift $shift, string $date): array
    {
        if ($animalIds === []) {
            return [];
        }

        $ranked = MilkProductionRecord::query()->toBase()
            ->select(['animal_id', 'production_date', 'quantity_litre'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY animal_id ORDER BY production_date DESC) AS rn')
            ->whereIn('animal_id', $animalIds)
            ->where('shift', $shift->value)
            ->where('production_date', '<', $date)
            ->where('production_date', '>=', Carbon::parse($date)->subDays(self::HINT_DAYS)->toDateString());

        $hints = [];
        DB::query()->fromSub($ranked, 'r')->where('rn', '<=', 3)->orderBy('animal_id')->orderBy('rn')->get()
            ->each(function ($row) use (&$hints) {
                $hints[(int) $row->animal_id][] = ['date' => (string) $row->production_date, 'litres' => (string) $row->quantity_litre];
            });

        return $hints;
    }

    /**
     * One animal's milk per day for the last $days days (newest first), from one GROUP BY query — the profile's
     * Milk tab table and chart.
     *
     * @return list<array{date: string, morning: ?string, evening: ?string, total: string}>
     */
    public function animalHistory(Animal $animal, int $days = 60): array
    {
        return MilkProductionRecord::query()->toBase()
            ->where('animal_id', $animal->id)
            ->where('production_date', '>', today()->subDays($days)->toDateString())
            ->selectRaw('production_date')
            ->selectRaw("SUM(CASE WHEN shift = 'morning' THEN quantity_litre END) AS morning")
            ->selectRaw("SUM(CASE WHEN shift = 'evening' THEN quantity_litre END) AS evening")
            ->selectRaw('SUM(quantity_litre) AS total')
            ->groupBy('production_date')
            ->orderByDesc('production_date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->production_date,
                'morning' => $row->morning === null ? null : Money::round2((string) $row->morning),
                'evening' => $row->evening === null ? null : Money::round2((string) $row->evening),
                'total' => Money::round2((string) $row->total),
            ])->all();
    }

    /**
     * Save the grid in one transaction: blank = not touched, a new value creates the entry, a different value
     * updates it, the same value is left alone.
     *
     * @param  array<int, string|null>  $quantities  animal id => litres ("" / null = skip)
     * @return array{created: int, updated: int}
     */
    public function saveBulk(string $date, MilkShift $shift, array $quantities): array
    {
        $quantities = array_filter($quantities, fn ($q) => $q !== null && $q !== '');

        return DB::transaction(function () use ($date, $shift, $quantities) {
            $existing = $this->entriesFor($date, $shift, array_keys($quantities));
            $counts = ['created' => 0, 'updated' => 0];

            foreach ($quantities as $animalId => $litres) {
                $litres = Money::round2($litres);
                $record = $existing->get($animalId);

                if ($record === null) {
                    MilkProductionRecord::create([
                        'production_date' => $date, 'shift' => $shift, 'animal_id' => $animalId, 'quantity_litre' => $litres,
                    ]);
                    $counts['created']++;
                } elseif (Money::compare($record->quantity_litre, $litres) !== 0) {
                    $record->update(['quantity_litre' => $litres]);
                    $counts['updated']++;
                }
            }

            return $counts;
        });
    }

    /**
     * One entry from the single-entry form; idempotent on client_uuid (spec 4.7).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: MilkProductionRecord, 1: bool} the entry and whether it was newly created
     */
    public function create(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = MilkProductionRecord::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(fn () => MilkProductionRecord::create($this->attributes($data) + ['client_uuid' => $uuid])), true];
        } catch (UniqueConstraintViolationException $e) {
            // a double submit raced past the check above: the unique index kept the first one
            $existing = $uuid ? MilkProductionRecord::query()->where('client_uuid', $uuid)->first() : null;

            if ($existing === null) {
                throw $e;
            }

            return [$existing, false];
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(MilkProductionRecord $record, array $data): MilkProductionRecord
    {
        DB::transaction(fn () => $record->update($this->attributes($data)));

        return $record;
    }

    public function delete(MilkProductionRecord $record): void
    {
        DB::transaction(fn () => $record->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'production_date' => $data['production_date'],
            'shift' => $data['shift'],
            'animal_id' => $data['animal_id'],
            'quantity_litre' => Money::round2($data['quantity_litre']),
            'fat' => filled($data['fat'] ?? null) ? Money::round2($data['fat']) : null,
            'snf' => filled($data['snf'] ?? null) ? Money::round2($data['snf']) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
