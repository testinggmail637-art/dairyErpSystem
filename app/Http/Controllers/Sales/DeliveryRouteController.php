<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\DeliveryRouteRequest;
use App\Models\DeliveryRoute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Sales → Routes (spec Section 6, Phase 3): delivery routes with their delivery person and order. No delete — a route
 * is deactivated so customers and past deliveries keep it.
 */
class DeliveryRouteController extends Controller
{
    public function index(Request $request): View|Response
    {
        $filters = [
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
            'search' => mb_substr(trim((string) $request->query('search')), 0, 60),
        ];

        $routes = DeliveryRoute::query()
            ->with('deliveryUser:id,name')
            ->when($filters['status'], fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['search'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->ordered()
            ->paginate(50)
            ->appends(array_filter($filters));

        return $this->listView($request, 'sales.routes.index', [
            'routes' => $routes,
            'filters' => $filters,
            'canManage' => $request->user()->can('customers.manage'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new DeliveryRoute([
            'is_active' => true,
            'sort_order' => (int) DeliveryRoute::query()->max('sort_order') + 1,
        ]));
    }

    public function store(DeliveryRouteRequest $request): RedirectResponse
    {
        $route = DeliveryRoute::create($request->validated());

        return redirect()->route('delivery-routes.index')
            ->with('success', __('Route ":name" added.', ['name' => $route->name]));
    }

    public function edit(DeliveryRoute $deliveryRoute): View
    {
        return $this->form($deliveryRoute);
    }

    public function update(DeliveryRouteRequest $request, DeliveryRoute $deliveryRoute): RedirectResponse
    {
        $deliveryRoute->update($request->validated());

        return redirect()->route('delivery-routes.index')
            ->with('success', __('Route ":name" updated.', ['name' => $deliveryRoute->name]));
    }

    private function form(DeliveryRoute $route): View
    {
        return view('sales.routes.form', [
            'route' => $route,
            'deliveryUsers' => DeliveryRoute::deliveryUserOptions(),
        ]);
    }
}
