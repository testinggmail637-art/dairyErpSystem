<?php

namespace App\Http\Controllers\Collection;

use App\Enums\MilkType;
use App\Enums\RateMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\DeleteRateChartRequest;
use App\Http\Requests\Collection\RateChartRequest;
use App\Http\Requests\Collection\RatePreviewRequest;
use App\Models\Farmer;
use App\Models\RateChart;
use App\Services\RateChartService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Collection → Rate Charts (spec Phase 4) with a rate preview calculator. */
class RateChartController extends Controller
{
    public function __construct(private readonly RateChartService $charts) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'milk_type' => (string) $request->query('milk_type'),
            'status' => (string) $request->query('status'),
        ];

        $charts = RateChart::query()
            ->when(MilkType::tryFrom($filters['milk_type']), fn ($q, $type) => $q->where('milk_type', $type))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('milk_type')->orderByDesc('is_default')->orderByDesc('effective_from')
            ->paginate(25)->withQueryString();

        return $this->listView($request, 'collection.rate-charts.index', [
            'charts' => $charts,
            'filters' => $filters,
            'farmerCounts' => Farmer::query()->whereIn('rate_chart_id', $charts->getCollection()->modelKeys())
                ->groupBy('rate_chart_id')->selectRaw('rate_chart_id, COUNT(*) AS n')->pluck('n', 'rate_chart_id'),
        ]);
    }

    public function create(Request $request): View
    {
        $copy = $request->integer('copy') ? RateChart::with('slabs')->find($request->integer('copy')) : null;
        $chart = $copy ? $copy->replicate(['is_default'])->fill(['name' => $copy->name.' ('.__('copy').')', 'effective_from' => today()])
            : new RateChart(['milk_type' => MilkType::Cow, 'method' => RateMethod::Fixed, 'effective_from' => today(), 'is_active' => true]);

        return view('collection.rate-charts.form', ['chart' => $chart, 'slabs' => $copy ? $copy->slabs : collect()]);
    }

    public function store(RateChartRequest $request): RedirectResponse
    {
        $chart = $this->charts->create($request->validated());

        return redirect()->route('rate-charts.show', $chart)->with('success', __('Rate chart :name saved.', ['name' => $chart->name]));
    }

    public function show(RateChart $rateChart): View
    {
        return view('collection.rate-charts.show', [
            'chart' => $rateChart->load('slabs'),
            'farmers' => Farmer::query()->where('rate_chart_id', $rateChart->id)->orderBy('code')->limit(50)->get(['id', 'code', 'name']),
        ]);
    }

    public function edit(RateChart $rateChart): View
    {
        return view('collection.rate-charts.form', ['chart' => $rateChart, 'slabs' => $rateChart->slabs]);
    }

    public function update(RateChartRequest $request, RateChart $rateChart): RedirectResponse
    {
        $this->charts->update($rateChart, $request->validated());

        return redirect()->route('rate-charts.show', $rateChart)->with('success', __('Rate chart :name updated. Milk already collected keeps its old rate.', ['name' => $rateChart->name]));
    }

    public function destroy(DeleteRateChartRequest $request, RateChart $rateChart): RedirectResponse
    {
        try {
            $this->charts->delete($rateChart);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('rate-charts.index')->with('success', __('Rate chart :name deleted.', ['name' => $rateChart->name]));
    }

    /** Rate preview calculator (JSON): rate per litre, amount for the litres, below-standard flag. */
    public function preview(RatePreviewRequest $request, RateChart $rateChart): JsonResponse
    {
        $v = $request->validated();

        try {
            $result = $this->charts->calculate($rateChart->load('slabs'), $v['fat'] ?? null, $v['snf'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result + [
            'amount' => filled($v['qty'] ?? null) ? Money::mul($v['qty'], $result['rate']) : null,
            'rate_text' => inr($result['rate']),
            'amount_text' => filled($v['qty'] ?? null) ? inr(Money::mul($v['qty'], $result['rate'])) : null,
        ]);
    }
}
