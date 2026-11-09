<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\DeleteFarmerAdvanceRequest;
use App\Http\Requests\Collection\FarmerAdvanceRequest;
use App\Models\Farmer;
use App\Models\FarmerAdvance;
use App\Models\PaymentMode;
use App\Services\FarmerAdvanceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Collection → Farmer advances: list with totals, give an advance, delete with a reason (spec Phase 4). */
class FarmerAdvanceController extends Controller
{
    public function __construct(private readonly FarmerAdvanceService $advances) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->startOfMonth()->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
            'farmer_id' => (string) ($request->integer('farmer_id') ?: ''),
        ];

        $query = FarmerAdvance::query()->whereBetween('advance_date', [$filters['from'], $filters['to']])
            ->when($filters['farmer_id'], fn ($q, $v) => $q->where('farmer_id', $v));
        $totals = (clone $query)->toBase()->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amount')->first();

        $advances = $query->with(['farmer:id,code,name', 'paymentMode:id,name', 'creator:id,name'])
            ->orderByDesc('advance_date')->orderByDesc('id')
            ->paginate(50, ['*'], 'page', null, (int) $totals->cnt)
            ->appends(array_filter($filters));

        $farmer = $request->integer('farmer_id') ? Farmer::withTrashed()->find($request->integer('farmer_id'), ['id', 'code', 'name']) : null;

        return $this->listView($request, 'collection.advances.index', [
            'advances' => $advances,
            'filters' => $filters,
            'total' => Money::round2((string) $totals->amount),
            'count' => (int) $totals->cnt,
            'selectedFarmer' => $farmer ? [$farmer->id => $farmer->code.' · '.$farmer->name] : [],
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(FarmerAdvanceRequest $request): RedirectResponse
    {
        [$advance, $created] = $this->advances->create($request->validated());
        $advance->load('farmer:id,code,name');

        return back()->with('success', $created
            ? __('Advance of :amount to :code :name saved.', ['amount' => inr($advance->amount), 'code' => $advance->farmer->code, 'name' => $advance->farmer->name])
            : __('This advance was already saved.'));
    }

    public function destroy(DeleteFarmerAdvanceRequest $request, FarmerAdvance $farmerAdvance): RedirectResponse
    {
        $this->advances->delete($farmerAdvance);

        return back()->with('success', __('Advance deleted.'));
    }
}
