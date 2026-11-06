<?php

namespace App\Http\Controllers\Sales;

use App\Enums\DeliveryStatus;
use App\Enums\MilkShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\BulkDeliverRequest;
use App\Http\Requests\Sales\DispatchRequest;
use App\Http\Requests\Sales\UpdateDeliveryRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Services\DeliveryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin delivery grid (spec Section 8, Phase 3): every route for a date / shift, bulk mark delivered, edit a row. */
class DeliveryGridController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'date' => Carbon::canBeCreatedFromFormat((string) $request->query('date'), 'Y-m-d') ? (string) $request->query('date') : now()->toDateString(),
            'shift' => (string) $request->query('shift'),
            'route_id' => (string) $request->query('route_id'),
            'status' => (string) $request->query('status'),
            'search' => trim((string) $request->query('search')),
        ];

        $base = Delivery::query()
            ->where('delivery_date', $filters['date'])
            ->when(MilkShift::tryFrom($filters['shift']), fn ($q, $s) => $q->where('shift', $s))
            ->when($filters['route_id'] === 'none', fn ($q) => $q->whereNull('route_id'))
            ->when(ctype_digit($filters['route_id']), fn ($q) => $q->where('route_id', $filters['route_id']))
            ->when(DeliveryStatus::tryFrom($filters['status']), fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $c->withTrashed()->search($filters['search'])));

        $deliveries = (clone $base)
            ->with(['customer:id,code,name,route_sequence', 'product:id,name', 'route:id,name', 'deliveredBy:id,name'])
            ->orderBy('route_id')->orderBy('shift')
            ->orderBy(Customer::withTrashed()->select('route_sequence')->whereColumn('customers.id', 'deliveries.customer_id'))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $totals = (clone $base)->selectRaw('COUNT(*) as total, SUM(planned_qty) as planned, SUM(delivered_qty) as delivered, SUM(amount) as amount, SUM(status = ?) as pending',
            [DeliveryStatus::Pending->value])->first();

        return $this->listView($request, 'sales.deliveries.grid', [
            'deliveries' => $deliveries,
            'totals' => $totals,
            'filters' => $filters,
            'routes' => DeliveryRoute::query()->ordered()->pluck('name', 'id'),
            'today' => now()->toDateString(),
        ]);
    }

    public function bulkDeliver(BulkDeliverRequest $request): RedirectResponse
    {
        $count = $this->deliveries->markManyDelivered(array_map('intval', $request->validated('ids')));

        return back()->with('success', trans_choice(':count delivery marked delivered.|:count deliveries marked delivered.', $count, ['count' => $count]));
    }

    public function createDispatch(): View
    {
        $customerId = old('customer_id');
        $customer = $customerId ? Customer::query()->find($customerId, ['id', 'code', 'name']) : null;

        return view('sales.deliveries.dispatch', [
            'selectedCustomer' => $customer ? [$customer->id => "{$customer->code} · {$customer->name}"] : [],
            'products' => Product::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function storeDispatch(DispatchRequest $request): RedirectResponse
    {
        [$delivery, $created] = $this->deliveries->dispatch($request->validated());

        return redirect()->route('deliveries.grid', ['date' => $delivery->delivery_date->toDateString()])
            ->with('success', $created
                ? __('Dispatched :qty L to :name (:amount).', ['qty' => fmt_qty($delivery->delivered_qty), 'name' => $delivery->customer->name, 'amount' => inr($delivery->amount)])
                : __('This dispatch was already saved.'));
    }

    public function edit(Delivery $delivery): View
    {
        Gate::authorize('update', $delivery);

        return view('sales.deliveries.edit', ['delivery' => $delivery->load(['customer:id,code,name', 'product:id,name', 'route:id,name'])]);
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->deliveries->update($delivery, $request->validated());

        return redirect()->route('deliveries.grid', ['date' => $delivery->delivery_date->toDateString()])
            ->with('success', __('Delivery of :name updated.', ['name' => $delivery->customer->name]));
    }
}
