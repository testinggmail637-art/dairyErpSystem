<?php

namespace App\Http\Controllers\Herd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\DeleteVaccinationRequest;
use App\Http\Requests\Herd\VaccinationRequest;
use App\Models\Animal;
use App\Models\AnimalVaccination;
use App\Models\PaymentMode;
use App\Models\VaccineType;
use App\Services\VaccinationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Herd → Health → Vaccinations (spec Phase 5): list (given / due), give to one or many animals, edit, delete. */
class VaccinationController extends Controller
{
    public function __construct(private readonly VaccinationService $vaccinations) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->subDays(90)->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
            'vaccine_type_id' => (string) ($request->integer('vaccine_type_id') ?: ''),
            'animal_id' => (string) ($request->integer('animal_id') ?: ''),
            'due' => in_array($request->query('due'), ['30', 'overdue'], true) ? $request->query('due') : '',
        ];

        // "due" lists the latest dose of each animal + vaccine whose next date is overdue / within 30 days, any given date
        $query = AnimalVaccination::query()
            ->when($filters['due'], fn ($q, $due) => $q
                ->whereNotNull('next_due_on')
                ->where('next_due_on', $due === 'overdue' ? '<' : '<=', $due === 'overdue' ? today()->toDateString() : today()->addDays(30)->toDateString())
                ->whereNotExists(fn ($later) => $later->from('animal_vaccinations as l')
                    ->whereColumn('l.animal_id', 'animal_vaccinations.animal_id')->whereColumn('l.vaccine_type_id', 'animal_vaccinations.vaccine_type_id')
                    ->whereColumn('l.given_on', '>', 'animal_vaccinations.given_on')),
                fn ($q) => $q->whereBetween('given_on', [$filters['from'], $filters['to']]))
            ->when($filters['vaccine_type_id'], fn ($q, $v) => $q->where('vaccine_type_id', $v))
            ->when($filters['animal_id'], fn ($q, $v) => $q->where('animal_id', $v));
        $totals = (clone $query)->toBase()->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(cost), 0) AS cost')->first();

        $rows = $query->with(['animal:id,tag_no,name', 'vaccineType:id,name'])
            ->when($filters['due'], fn ($q) => $q->orderBy('next_due_on'), fn ($q) => $q->orderByDesc('given_on'))->orderByDesc('id')
            ->paginate(50, ['*'], 'page', null, (int) $totals->cnt)
            ->appends(array_filter($filters));

        $animal = $filters['animal_id'] ? Animal::withTrashed()->find($filters['animal_id'], ['id', 'tag_no', 'name']) : null;

        return $this->listView($request, 'herd.vaccinations.index', [
            'vaccinations' => $rows,
            'filters' => $filters,
            'count' => (int) $totals->cnt,
            'total' => Money::round2((string) $totals->cost),
            'vaccineTypes' => VaccineType::query()->orderBy('name')->pluck('name', 'id'),
            'selectedAnimal' => $animal ? [$animal->id => $animal->label()] : [],
            'canManage' => $request->user()->can('health.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $ids = (array) $request->old('animal_ids', array_filter([$request->integer('animal_id')]));

        return view('herd.vaccinations.form', $this->formData(new AnimalVaccination(['given_on' => today()]), $ids, true,
            PaymentMode::query()->where('code', 'CASH')->value('id'), true));
    }

    public function store(VaccinationRequest $request): RedirectResponse
    {
        $count = $this->vaccinations->createBulk($request->validated());

        return redirect()->route('health.vaccinations.index')->with('success', trans_choice('{1} Vaccination saved.|[2,*] :count vaccinations saved.', $count, ['count' => $count]));
    }

    public function edit(AnimalVaccination $vaccination): View
    {
        $vaccination->load('expense');

        return view('herd.vaccinations.form', $this->formData($vaccination, (array) old('animal_ids', [$vaccination->animal_id]),
            $vaccination->expense_id !== null, $vaccination->expense?->payment_mode_id, false));
    }

    public function update(VaccinationRequest $request, AnimalVaccination $vaccination): RedirectResponse
    {
        $this->vaccinations->update($vaccination, $request->validated());

        return redirect()->route('health.vaccinations.index')->with('success', __('Vaccination saved.'));
    }

    public function destroy(DeleteVaccinationRequest $request, AnimalVaccination $vaccination): RedirectResponse
    {
        $this->vaccinations->delete($vaccination);

        return back()->with('success', __('Vaccination deleted (with its expense, if any).'));
    }

    /**
     * @param  list<int|string>  $animalIds
     * @return array<string, mixed>
     */
    private function formData(AnimalVaccination $row, array $animalIds, bool $recordAsExpense, ?int $paymentModeId, bool $bulk): array
    {
        $types = VaccineType::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $row->vaccine_type_id))
            ->orderBy('name')->get(['id', 'name', 'interval_days']);

        return [
            'vaccination' => $row,
            'bulk' => $bulk,
            'animalOptions' => Animal::query()->whereKey($animalIds)->orderBy('tag_no')->get(['id', 'tag_no', 'name'])
                ->mapWithKeys(fn (Animal $a) => [$a->id => $a->label()])->all(),
            'vaccineTypes' => $types->mapWithKeys(fn ($t) => [$t->id => $t->name.($t->interval_days ? ' · '.__('every :n days', ['n' => $t->interval_days]) : '')]),
            'recordAsExpense' => $recordAsExpense,
            'paymentModeId' => $paymentModeId,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
