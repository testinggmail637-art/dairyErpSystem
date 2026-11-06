<?php

namespace App\Http\Controllers\Sales;

use App\Enums\DeliveryStatus;
use App\Enums\MilkShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CollectPaymentRequest;
use App\Http\Requests\Sales\DeliverRequest;
use App\Http\Requests\Sales\SkipDeliveryRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Models\PaymentMode;
use App\Services\CustomerPaymentService;
use App\Services\DeliveryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Delivery person's mobile sheet (spec Section 6): date + shift + his route(s), customers in route order, each with
 * Delivered / Skip / litres and Collect payment. Users with deliveries.view_all_routes may open any route.
 */
class DeliverySheetController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $date = Carbon::canBeCreatedFromFormat((string) $request->query('date'), 'Y-m-d')
            ? (string) $request->query('date') : now()->toDateString();
        $shift = MilkShift::tryFrom((string) $request->query('shift')) ?? MilkShift::current();

        $routes = DeliveryRoute::query()->ordered()
            ->unless($user->can('deliveries.view_all_routes'), fn ($q) => $q->where('delivery_user_id', $user->id))
            ->pluck('name', 'id');
        $routeId = $routes->has((int) $request->query('route_id')) ? (int) $request->query('route_id') : null;

        $base = Delivery::query()
            ->where('delivery_date', $date)
            ->where('shift', $shift)
            ->when($routeId, fn ($q) => $q->where('route_id', $routeId))
            ->unless($routeId || $user->can('deliveries.view_all_routes'), fn ($q) => $q->whereIn('route_id', $routes->keys()));

        $deliveries = (clone $base)
            ->with(['customer:id,code,name,phone,address,area,route_sequence', 'product:id,name'])
            ->orderBy('route_id')
            ->orderBy(Customer::withTrashed()->select('route_sequence')->whereColumn('customers.id', 'deliveries.customer_id'))
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        $totals = (clone $base)->selectRaw('COUNT(*) as total, SUM(planned_qty) as planned, SUM(delivered_qty) as delivered, SUM(status = ?) as pending',
            [DeliveryStatus::Pending->value])->first();

        $collected = CustomerPayment::query()
            ->where('payment_date', now()->toDateString())
            ->whereIn('customer_id', $deliveries->pluck('customer_id')->unique())
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(amount) as total')
            ->pluck('total', 'customer_id');

        return view('sales.deliveries.sheet', [
            'date' => $date,
            'shift' => $shift,
            'routes' => $routes,
            'routeId' => $routeId,
            'deliveries' => $deliveries,
            'totals' => $totals,
            'collected' => $collected,
            'paymentModes' => $user->can('customer_payments.create') ? PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id') : collect(),
            'today' => now()->toDateString(),
        ]);
    }

    public function deliver(DeliverRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->deliveries->markDelivered($delivery, $request->validated('delivered_qty'));

        return $this->backToRow($delivery, __(':name: delivered :qty L.', ['name' => $delivery->customer->name, 'qty' => fmt_qty($delivery->delivered_qty)]));
    }

    public function skip(SkipDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->deliveries->skip($delivery, $request->validated('skip_reason'));

        return $this->backToRow($delivery, __(':name: skipped.', ['name' => $delivery->customer->name]));
    }

    public function collect(CollectPaymentRequest $request, Delivery $delivery, CustomerPaymentService $payments): RedirectResponse
    {
        [$payment] = $payments->record($request->validated() + [
            'customer_id' => $delivery->customer_id,
            'payment_date' => now()->toDateString(),
        ]);

        return $this->backToRow($delivery, __(':name: received :amount.', ['name' => $delivery->customer->name, 'amount' => inr($payment->amount)]));
    }

    private function backToRow(Delivery $delivery, string $message): RedirectResponse
    {
        return redirect()->to(url()->previous().'#delivery-'.$delivery->id)->with('success', $message);
    }
}
