<?php

namespace App\Http\Controllers\Sales;

use App\Enums\MilkShift;
use App\Enums\MilkSource;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SubscriptionPauseRequest;
use App\Http\Requests\Sales\SubscriptionRequest;
use App\Models\Customer;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\SubscriptionPause;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Sales → Subscriptions (spec Section 6, Phase 3): daily milk per customer, product and shift, with pauses. */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'customer_id' => (string) $request->query('customer_id'),
            'route_id' => (string) $request->query('route_id'),
            'product_id' => (string) $request->query('product_id'),
            'shift' => (string) $request->query('shift'),
            'status' => (string) $request->query('status'),
        ];

        $subscriptions = Subscription::query()
            ->with(['customer:id,code,name,route_id,route_sequence', 'customer.route:id,name', 'product:id,name'])
            ->withPausedOn(now()->toDateString())
            ->when($filters['search'] !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $c->search($filters['search'])))
            ->when(ctype_digit($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(ctype_digit($filters['route_id']), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('route_id', $filters['route_id'])))
            ->when(ctype_digit($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(MilkShift::tryFrom($filters['shift']), fn ($q, $shift) => $q->where('shift', $shift))
            ->when(SubscriptionStatus::tryFrom($filters['status']), fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'sales.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'routes' => DeliveryRoute::query()->ordered()->pluck('name', 'id')->all(),
            'products' => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer_id') ?: null;

        return view('sales.subscriptions.form', $this->formData(new Subscription([
            'customer_id' => $customerId && Customer::whereKey($customerId)->exists() ? $customerId : null,
            'shift' => MilkShift::Morning,
            'stock_source' => MilkSource::OwnFarm,
            'start_date' => now()->addDay()->startOfDay(),
        ])));
    }

    public function store(SubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->subscriptions->create($request->validated());

        return redirect()->route('subscriptions.edit', $subscription)->with('success', __('Subscription added.'));
    }

    public function edit(Subscription $subscription): View
    {
        return view('sales.subscriptions.form', $this->formData($subscription) + [
            'pauses' => $subscription->pauses()->orderByDesc('from_date')->paginate(25),
        ]);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptions->update($subscription, $request->validated());

        return redirect()->route('subscriptions.edit', $subscription)->with('success', __('Subscription updated.'));
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $this->subscriptions->delete($subscription);

        return redirect()->route('subscriptions.index')->with('success', __('Subscription deleted.'));
    }

    public function storePause(SubscriptionPauseRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptions->addPause($subscription, $request->validated());

        return redirect()->route('subscriptions.edit', $subscription)->with('success', __('Pause added.'));
    }

    public function destroyPause(Subscription $subscription, SubscriptionPause $pause): RedirectResponse
    {
        $pause->delete();

        return redirect()->route('subscriptions.edit', $subscription)->with('success', __('Pause removed.'));
    }

    /** @return array<string, mixed> */
    private function formData(Subscription $subscription): array
    {
        $customerId = old('customer_id', $subscription->customer_id);
        $customer = $customerId ? Customer::withTrashed()->find($customerId, ['id', 'code', 'name']) : null;

        return [
            'subscription' => $subscription,
            'selectedCustomer' => $customer ? [$customer->id => "{$customer->code} · {$customer->name}"] : [],
            'products' => Product::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $subscription->product_id))
                ->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
