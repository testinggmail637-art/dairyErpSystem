<?php

namespace App\Http\Controllers\Collection;

use App\Enums\MilkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\DeleteFarmerRequest;
use App\Http\Requests\Collection\FarmerRequest;
use App\Models\Farmer;
use App\Models\PaymentMode;
use App\Models\RateChart;
use App\Services\FarmerAdvanceService;
use App\Services\FarmerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Collection → Farmers (spec Phase 4). */
class FarmerController extends Controller
{
    public function __construct(private readonly FarmerService $farmers) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'milk_type' => (string) $request->query('milk_type'),
            'status' => (string) $request->query('status'),
        ];

        $farmers = Farmer::query()
            ->search($filters['search'])
            ->when(MilkType::tryFrom($filters['milk_type']), fn ($q, $type) => $q->where('default_milk_type', $type))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('code')
            ->paginate(50)
            ->withQueryString();

        return $this->listView($request, 'collection.farmers.index', compact('farmers', 'filters'));
    }

    public function create(): View
    {
        return view('collection.farmers.form', $this->formData(new Farmer([
            'default_milk_type' => MilkType::Cow, 'is_active' => true, 'opening_balance' => '0.00',
        ])));
    }

    public function store(FarmerRequest $request): RedirectResponse
    {
        $farmer = $this->farmers->create($request->validated());

        return redirect()->route('farmers.show', $farmer)
            ->with('success', __('Farmer :name added as :code.', ['name' => $farmer->name, 'code' => $farmer->code]));
    }

    public function show(Request $request, Farmer $farmer, FarmerAdvanceService $advances): View
    {
        $canAdvance = $request->user()->can('farmer_advances.manage');

        return view('collection.farmers.show', [
            'farmer' => $farmer->load('rateChart:id,name'),
            'canAdvance' => $canAdvance,
            'outstandingAdvance' => $canAdvance ? $advances->outstanding($farmer->id) : null,
            'advances' => $canAdvance ? $farmer->advances()->with('paymentMode:id,name')->latest('advance_date')->latest('id')->limit(10)->get() : collect(),
            'paymentModes' => $canAdvance ? PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function edit(Farmer $farmer): View
    {
        return view('collection.farmers.form', $this->formData($farmer));
    }

    public function update(FarmerRequest $request, Farmer $farmer): RedirectResponse
    {
        $this->farmers->update($farmer, $request->validated());

        return redirect()->route('farmers.show', $farmer)->with('success', __('Farmer :name updated.', ['name' => $farmer->name]));
    }

    public function destroy(DeleteFarmerRequest $request, Farmer $farmer): RedirectResponse
    {
        try {
            $this->farmers->delete($farmer);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('farmers.index')->with('success', __('Farmer :name deleted.', ['name' => $farmer->name]));
    }

    /** @return array<string, mixed> */
    private function formData(Farmer $farmer): array
    {
        // own rate chart: active charts (or the farmer's current one), grouped by milk in the label
        $charts = RateChart::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $farmer->rate_chart_id))
            ->orderBy('milk_type')->orderBy('name')->get(['id', 'name', 'milk_type']);

        return ['farmer' => $farmer, 'rateCharts' => $charts->mapWithKeys(fn ($c) => [$c->id => $c->milk_type->label().' · '.$c->name])->all()];
    }

    /**
     * Select2 / collection-entry search: active farmers by code (7 → F-007), name, village or phone, 20 per page.
     * Response: {"results": [{"id": 1, "text": "F-007 · Ramesh Patil · Wadgaon", "milk_type": "cow"}], "pagination": {"more": false}}
     */
    public function search(Request $request): JsonResponse
    {
        $farmers = Farmer::query()->active()
            ->search($request->query('q'))
            ->orderBy('code')
            ->simplePaginate(20, ['id', 'code', 'name', 'village', 'default_milk_type'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'results' => $farmers->getCollection()->map(fn (Farmer $f) => [
                'id' => $f->id,
                'text' => collect([$f->code, $f->name, $f->village])->filter()->implode(' · '),
                'milk_type' => $f->default_milk_type->value,
            ])->values(),
            'pagination' => ['more' => $farmers->hasMorePages()],
        ]);
    }
}
