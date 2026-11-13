<?php

namespace App\Http\Controllers\Herd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\DeleteTreatmentRequest;
use App\Http\Requests\Herd\TreatmentRequest;
use App\Models\Animal;
use App\Models\PaymentMode;
use App\Models\Treatment;
use App\Services\TreatmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Herd → Health → Treatments (spec Phase 5): list with cost total, add / edit / delete; linked expense kept in step. */
class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentService $treatments) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->subDays(90)->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
            'animal_id' => (string) ($request->integer('animal_id') ?: ''),
            'withdrawal' => $request->boolean('withdrawal') ? '1' : '',
        ];

        $query = Treatment::query()
            ->when($filters['withdrawal'],
                fn ($q) => $q->where('milk_withdrawal_until', '>=', today()->toDateString()),
                fn ($q) => $q->whereBetween('treatment_date', [$filters['from'], $filters['to']]))
            ->when($filters['animal_id'], fn ($q, $v) => $q->where('animal_id', $v));
        $totals = (clone $query)->toBase()->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(cost), 0) AS cost')->first();

        $rows = $query->with('animal:id,tag_no,name')
            ->orderByDesc('treatment_date')->orderByDesc('id')
            ->paginate(50, ['*'], 'page', null, (int) $totals->cnt)
            ->appends(array_filter($filters));

        $animal = $filters['animal_id'] ? Animal::withTrashed()->find($filters['animal_id'], ['id', 'tag_no', 'name']) : null;

        return $this->listView($request, 'herd.treatments.index', [
            'treatments' => $rows,
            'filters' => $filters,
            'count' => (int) $totals->cnt,
            'total' => Money::round2((string) $totals->cost),
            'selectedAnimal' => $animal ? [$animal->id => $animal->label()] : [],
            'canManage' => $request->user()->can('health.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $animal = $request->integer('animal_id') ? Animal::find($request->integer('animal_id'), ['id', 'tag_no', 'name']) : null;

        return view('herd.treatments.form', $this->formData(new Treatment([
            'treatment_date' => today(), 'animal_id' => $animal?->id,
        ]), $animal, true, PaymentMode::query()->where('code', 'CASH')->value('id')));
    }

    public function store(TreatmentRequest $request): RedirectResponse
    {
        $treatment = $this->treatments->create($request->validated());

        return redirect()->route('health.treatments.index')->with('success', $treatment->expense_id
            ? __('Treatment saved and :amount added to expenses (Health → Medicine).', ['amount' => inr($treatment->cost)])
            : __('Treatment saved.'));
    }

    public function edit(Treatment $treatment): View
    {
        $treatment->load('animal:id,tag_no,name', 'expense');

        return view('herd.treatments.form', $this->formData($treatment, $treatment->animal,
            $treatment->expense_id !== null, $this->treatments->paymentModeId($treatment)));
    }

    public function update(TreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $this->treatments->update($treatment, $request->validated());

        return redirect()->route('health.treatments.index')->with('success', __('Treatment saved.'));
    }

    public function destroy(DeleteTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $this->treatments->delete($treatment);

        return back()->with('success', __('Treatment deleted (with its expense, if any).'));
    }

    /** @return array<string, mixed> */
    private function formData(Treatment $treatment, ?Animal $animal, bool $recordAsExpense, ?int $paymentModeId): array
    {
        return [
            'treatment' => $treatment,
            'animalOptions' => $animal ? [$animal->id => $animal->label()] : [],
            'recordAsExpense' => $recordAsExpense,
            'paymentModeId' => $paymentModeId,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
