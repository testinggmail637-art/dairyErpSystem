<?php

namespace App\Services;

use App\Enums\MilkShift;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Milk drop alert (spec 7.3): for each animal and shift, the average of its previous 7 entries of that shift
 * (at least 3 needed) against the day's entry; drop % = (avg − today) / avg × 100, alert at or above the farm's
 * `milk_drop_alert_percent`. A signal, not a diagnosis. One SQL query with ROW_NUMBER(); percentages in bcmath.
 */
class MilkDropAlertService
{
    /** Entries counted for the average. */
    public const HISTORY_ENTRIES = 7;

    /** Fewer previous entries than this → no alert (not enough history). */
    public const MIN_ENTRIES = 3;

    /** Entries older than this many days before the date do not count (a long gap is not a "normal" yield). */
    public const LOOKBACK_DAYS = 30;

    public function __construct(private readonly CurrentFarm $currentFarm) {}

    public function threshold(): int
    {
        return (int) ($this->currentFarm->farm()?->setting('milk_drop_alert_percent') ?? 20);
    }

    /**
     * Alerts of one date (optionally one shift), biggest drop first.
     *
     * @return Collection<int, object{animal: Animal, shift: MilkShift, today: string, average: string, entries: int, drop: string}>
     */
    public function alerts(string $date, ?MilkShift $shift = null): Collection
    {
        $date = Carbon::parse($date)->toDateString();
        $threshold = (string) $this->threshold();

        // previous entries of the same animal and shift, newest first, numbered per animal + shift
        $ranked = MilkProductionRecord::query()->toBase()
            ->select(['animal_id', 'shift', 'quantity_litre'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY animal_id, shift ORDER BY production_date DESC) AS rn')
            ->where('production_date', '<', $date)
            ->where('production_date', '>=', Carbon::parse($date)->subDays(self::LOOKBACK_DAYS)->toDateString())
            ->when($shift, fn ($q) => $q->where('shift', $shift->value));

        $history = DB::query()->fromSub($ranked, 'h')
            ->where('rn', '<=', self::HISTORY_ENTRIES)
            ->groupBy('animal_id', 'shift')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_ENTRIES])
            ->selectRaw('animal_id, shift, COUNT(*) AS entries, AVG(quantity_litre) AS average');

        $rows = MilkProductionRecord::query()->toBase()
            ->joinSub($history, 'h', fn ($j) => $j->on('h.animal_id', '=', 'milk_production_records.animal_id')
                ->on('h.shift', '=', 'milk_production_records.shift'))
            ->where('milk_production_records.production_date', $date)
            ->when($shift, fn ($q) => $q->where('milk_production_records.shift', $shift->value))
            ->select(['milk_production_records.animal_id', 'milk_production_records.shift', 'milk_production_records.quantity_litre', 'h.entries', 'h.average'])
            ->get();

        $alerts = $rows->map(function ($row) {
            $average = (string) $row->average;

            return (object) [
                'animal_id' => (int) $row->animal_id,
                'shift' => MilkShift::from($row->shift),
                'today' => Money::round2((string) $row->quantity_litre),
                'average' => Money::round2($average),
                'entries' => (int) $row->entries,
                'drop' => bccomp($average, '0', 4) > 0
                    ? Money::round(bcdiv(bcmul(bcsub($average, (string) $row->quantity_litre, 6), '100', 6), $average, 6), 1)
                    : '0.0',
            ];
        })->filter(fn ($a) => bccomp($a->drop, $threshold, 1) >= 0);

        $animals = Animal::withTrashed()->with('breed:id,name')
            ->findMany($alerts->pluck('animal_id')->unique(), ['id', 'tag_no', 'name', 'animal_type', 'breed_id'])
            ->keyBy('id');

        return $alerts->map(function ($a) use ($animals) {
            $a->animal = $animals->get($a->animal_id);

            return $a;
        })->sort(fn ($x, $y) => bccomp($y->drop, $x->drop, 1) ?: strcmp($x->animal->tag_no, $y->animal->tag_no))->values();
    }
}
