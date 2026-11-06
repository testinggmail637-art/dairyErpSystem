<?php

namespace App\Http\Controllers\Sales;

use App\Enums\B2bCategory;
use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CustomerAdjustmentRequest;
use App\Http\Requests\Sales\CustomerRateRequest;
use App\Http\Requests\Sales\CustomerRequest;
use App\Http\Requests\Sales\DeleteCustomerAdjustmentRequest;
use App\Http\Requests\Sales\DeleteCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerAdjustment;
use App\Models\CustomerRate;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Services\CustomerService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Sales → Customers (spec Section 6, Phase 3) with their own rates per product. */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type' => (string) $request->query('type'),
            'route_id' => (string) $request->query('route_id'),
            'status' => (string) $request->query('status'),
        ];

        $customers = Customer::query()
            ->with('route:id,name')
            ->search($filters['search'])
            ->when(CustomerType::tryFrom($filters['type']), fn ($q, $type) => $q->where('customer_type', $type))
            ->when($filters['route_id'] === 'none', fn ($q) => $q->whereNull('route_id'))
            ->when(ctype_digit($filters['route_id']), fn ($q) => $q->where('route_id', $filters['route_id'])->orderBy('route_sequence'))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'sales.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'routes' => DeliveryRoute::query()->ordered()->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        return view('sales.customers.form', $this->formData(new Customer([
            'customer_type' => CustomerType::B2c, 'is_active' => true, 'opening_balance' => '0.00',
        ])));
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Customer :name added as :code.', ['name' => $customer->name, 'code' => $customer->code]));
    }

    public function show(Request $request, Customer $customer, PricingService $pricing): View
    {
        $customer->load('route:id,name');
        $today = now()->toDateString();
        $products = Product::query()->active()->orderBy('name')->get();

        return view('sales.customers.show', [
            'customer' => $customer,
            'currentRates' => $products->map(fn (Product $p) => ['product' => $p, 'rate' => $pricing->rateFor($customer, $p, $today)]),
            'productOptions' => $products->pluck('name', 'id')->all(),
            'rates' => $customer->rates()->with('product:id,name')->orderByDesc('effective_from')->orderBy('product_id')->paginate(25),
            'canManage' => $request->user()->can('customers.manage'),
            'adjustments' => $customer->adjustments()->with('creator:id,name')
                ->orderByDesc('adjustment_date')->orderByDesc('id')->paginate(10, ['*'], 'adj_page'),
        ]);
    }

    public function storeAdjustment(CustomerAdjustmentRequest $request, Customer $customer): RedirectResponse
    {
        $customer->adjustments()->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('Adjustment saved.'));
    }

    public function destroyAdjustment(DeleteCustomerAdjustmentRequest $request, Customer $customer, CustomerAdjustment $adjustment): RedirectResponse
    {
        $adjustment->delete();

        return redirect()->route('customers.show', $customer)->with('success', __('Adjustment removed.'));
    }

    public function edit(Customer $customer): View
    {
        return view('sales.customers.form', $this->formData($customer));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Customer :name updated.', ['name' => $customer->name]));
    }

    public function destroy(DeleteCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->delete($customer);

        return redirect()->route('customers.index')
            ->with('success', __('Customer :name deleted.', ['name' => $customer->name]));
    }

    public function storeRate(CustomerRateRequest $request, Customer $customer): RedirectResponse
    {
        $this->customers->addRate($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('Rate saved.'));
    }

    public function destroyRate(DeleteCustomerRequest $request, Customer $customer, CustomerRate $rate): RedirectResponse
    {
        $rate->delete();

        return redirect()->route('customers.show', $customer)->with('success', __('Rate removed.'));
    }

    /**
     * Select2 remote search: active customers by name, code or phone, 20 per page.
     * Response: {"results": [{"id": 1, "text": "C-0001 · Ramesh Patil · 9876543210"}], "pagination": {"more": false}}
     */
    public function search(Request $request): JsonResponse
    {
        $customers = Customer::query()->active()
            ->search($request->query('q'))
            ->orderBy('name')
            ->simplePaginate(20, ['id', 'code', 'name', 'phone'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'results' => $customers->getCollection()->map(fn (Customer $c) => [
                'id' => $c->id,
                'text' => collect([$c->code, $c->name, $c->phone])->filter()->implode(' · '),
            ])->values(),
            'pagination' => ['more' => $customers->hasMorePages()],
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Customer $customer): array
    {
        return [
            'customer' => $customer,
            'routes' => DeliveryRoute::query()
                ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $customer->route_id))
                ->ordered()->pluck('name', 'id')->all(),
            'types' => CustomerType::options(),
            'categories' => B2bCategory::options(),
        ];
    }
}
