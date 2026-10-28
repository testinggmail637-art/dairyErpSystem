<?php

namespace App\Http\Controllers\Herd;

use App\Enums\AnimalType;
use App\Enums\MilkShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\BulkMilkEntryRequest;
use App\Http\Requests\Herd\DeleteMilkEntryRequest;
use App\Http\Requests\Herd\MilkEntryRequest;
use App\Models\Animal;
use App\Models\MilkProductionRecord;
use App\Services\MilkDropAlertService;
use App\Services\MilkProductionService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Herd → Milk Entry (spec 7.3): the date + shift grid (primary), the list of entries, and single entry add/edit/delete.
 */
class MilkProductionController extends Controller
{
    public function __construct(
        private readonly MilkProductionService $milk,
        private readonly MilkDropAlertService $alerts,
    ) {}

    /** The grid: every milking animal for one date and shift, saved litres pre-filled, last 3 entries as a hint. */
    public function index(Request $request): View
    {
        $date = $this->dateFrom($request->query('date'));
        $shift = MilkShift::tryFrom((string) $request->query('shift')) ?? MilkShift::current();

        $animals = $this->milk->gridAnimals($date, $shift);
        $ids = $animals->pluck('id')->all();

        return view('herd.milk.grid', [
            'date' => $date,
            'shift' => $shift,
            'animals' => $animals,
            'entries' => $this->milk->entriesFor($date, $shift, $ids),
            'hints' => $this->milk->lastEntries($ids, $shift, $date),
            'canCreate' => $request->user()->can('create', MilkProductionRecord::class),
            'alerts' => $this->alerts->alerts($date, $shift),
        ]);
    }

    /** Milk drop alerts of a date, both shifts (spec 7.3). */
    public function alerts(Request $request): View
    {
        $date = $this->dateFrom($request->query('date'));

        return view('herd.milk.alerts', [
            'date' => $date,
            'alerts' => $this->alerts->alerts($date),
            'threshold' => $this->alerts->threshold(),
        ]);
    }

    public function storeBulk(BulkMilkEntryRequest $request): RedirectResponse
    {
        $date = $request->validated('production_date');
        $shift = $request->shift();
        $counts = $this->milk->saveBulk($date, $shift, $request->validated('qty', []));

        return redirect()->route('milk-production.index', ['date' => $date, 'shift' => $shift->value])
            ->with('success', __(':shift milk of :date saved: :created new, :updated changed.', [
                'shift' => $shift->label(), 'date' => fmt_date($date), 'created' => $counts['created'], 'updated' => $counts['updated'],
            ]));
    }

    /** All entries with filters, litres per milk type for the filtered set (SQL), 50 per page. */
    public function entries(Request $request): View|Response
    {
        $filters = [
            'from' => $this->validDate($request->query('from')) ?? now()->startOfMonth()->toDateString(),
            'to' => $this->validDate($request->query('to')) ?? today()->toDateString(),
            'shift' => MilkShift::tryFrom((string) $request->query('shift'))?->value ?? '',
            'type' => AnimalType::tryFrom((string) $request->query('type'))?->value ?? '',
            'animal_id' => (string) ($request->integer('animal_id') ?: ''),
        ];

        $query = MilkProductionRecord::query()
            ->whereBetween('production_date', [$filters['from'], $filters['to']])
            ->when($filters['shift'], fn ($q, $v) => $q->where('shift', $v))
            ->when($filters['animal_id'], fn ($q, $v) => $q->where('animal_id', $v))
            ->when($filters['type'], fn ($q, $v) => $q->whereHas('animal', fn ($a) => $a->where('animal_type', $v)));

        // litres per milk type: group by animal first (index only), then join the few animals — not every row
        $perAnimal = (clone $query)->toBase()->reorder()
            ->selectRaw('animal_id, COUNT(*) AS entries, SUM(quantity_litre) AS litres')
            ->groupBy('animal_id');
        $totals = DB::query()->fromSub($perAnimal, 'r')
            ->join('animals', 'animals.id', '=', 'r.animal_id')
            ->selectRaw('animals.animal_type AS type, SUM(r.entries) AS entries, COALESCE(SUM(r.litres), 0) AS litres')
            ->groupBy('animals.animal_type')
            ->get()->keyBy('type');

        // newest first, morning before evening; one direction so the (farm, date, shift, animal) index serves the order
        $records = $query->select(['id', 'production_date', 'shift', 'animal_id', 'quantity_litre', 'fat', 'snf', 'created_by'])
            ->with(['animal:id,tag_no,name,animal_type', 'creator:id,name'])
            ->orderByDesc('production_date')->orderByDesc('shift')->orderByDesc('animal_id')
            ->paginate(50, ['*'], 'page', null, (int) $totals->sum('entries')) // reuse the totals count: no second COUNT(*)
            ->appends(array_filter($filters));

        $animal = $filters['animal_id'] ? Animal::withTrashed()->find((int) $filters['animal_id'], ['id', 'tag_no', 'name']) : null;

        return $this->listView($request, 'herd.milk.entries', [
            'records' => $records,
            'filters' => $filters,
            'totals' => $totals,
            'grandTotal' => Money::sum($totals->pluck('litres')),
            'selectedAnimal' => $animal ? [$animal->id => $animal->label()] : [],
        ]);
    }

    public function create(Request $request): View
    {
        $animal = $request->integer('animal_id') ? Animal::query()->milking()->find($request->integer('animal_id')) : null;

        return view('herd.milk.form', [
            'record' => new MilkProductionRecord([
                'production_date' => $this->dateFrom($request->query('date')),
                'shift' => MilkShift::tryFrom((string) $request->query('shift')) ?? MilkShift::current(),
                'animal_id' => $animal?->id,
            ]),
            'selectedAnimal' => $this->animalOption(old('animal_id', $animal?->id)),
        ]);
    }

    public function store(MilkEntryRequest $request): RedirectResponse
    {
        [$record, $created] = $this->milk->create($request->validated());

        return redirect()->route('milk-production.entries', ['from' => $record->production_date->toDateString(), 'to' => $record->production_date->toDateString()])
            ->with('success', $created
                ? __('Milk entry saved: :litres L.', ['litres' => fmt_qty($record->quantity_litre)])
                : __('This entry was already saved.'));
    }

    public function edit(MilkProductionRecord $record): View
    {
        return view('herd.milk.form', [
            'record' => $record,
            'selectedAnimal' => $this->animalOption(old('animal_id', $record->animal_id)),
        ]);
    }

    public function update(MilkEntryRequest $request, MilkProductionRecord $record): RedirectResponse
    {
        $this->milk->update($record, $request->validated());

        return redirect()->route('milk-production.entries', ['from' => $record->production_date->toDateString(), 'to' => $record->production_date->toDateString()])
            ->with('success', __('Milk entry updated.'));
    }

    public function destroy(DeleteMilkEntryRequest $request, MilkProductionRecord $record): RedirectResponse
    {
        $this->milk->delete($record);

        return back()->with('success', __('Milk entry deleted.'));
    }

    /** ?date= as Y-m-d, never in the future; anything else → today. */
    private function dateFrom(mixed $value): string
    {
        $date = $this->validDate($value);

        return $date && $date <= today()->toDateString() ? $date : today()->toDateString();
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->toDateString() === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, string> */
    private function animalOption(mixed $animalId): array
    {
        $animal = $animalId ? Animal::withTrashed()->find((int) $animalId, ['id', 'tag_no', 'name']) : null;

        return $animal ? [$animal->id => $animal->label()] : [];
    }
}
