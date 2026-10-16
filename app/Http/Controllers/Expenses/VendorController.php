<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\VendorRequest;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Expenses → Vendors. The ledger with balance and payments comes in Task 1.5.
 */
class VendorController extends Controller
{
    public function __construct(private readonly VendorService $vendors) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
        ];

        $vendors = Vendor::query()
            ->search($filters['search'])
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'expenses.vendors.index', compact('vendors', 'filters'));
    }

    public function create(): View
    {
        return view('expenses.vendors.form', ['vendor' => new Vendor(['is_active' => true, 'opening_balance' => '0.00'])]);
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $vendor = $this->vendors->create($request->validated());

        return redirect()->route('vendors.index')->with('success', __('Vendor :name added.', ['name' => $vendor->name]));
    }

    public function edit(Vendor $vendor): View
    {
        return view('expenses.vendors.form', ['vendor' => $vendor]);
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->update($vendor, $request->validated());

        return redirect()->route('vendors.index')->with('success', __('Vendor :name updated.', ['name' => $vendor->name]));
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->vendors->delete($vendor);

        return redirect()->route('vendors.index')->with('success', __('Vendor :name deleted.', ['name' => $vendor->name]));
    }

    /**
     * Select2 remote search (expense form): active vendors only, 20 per page.
     * Response: {"results": [{"id": 1, "text": "Sharma Feeds · 98765 43210"}], "pagination": {"more": false}}
     */
    public function search(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));

        $vendors = Vendor::query()->active()
            ->search($request->query('q'))
            ->orderBy('name')
            ->simplePaginate(20, ['id', 'name', 'phone'], 'page', $page);

        return response()->json([
            'results' => $vendors->getCollection()->map(fn (Vendor $v) => [
                'id' => $v->id,
                'text' => $v->phone ? "{$v->name} · {$v->phone}" : $v->name,
            ])->values(),
            'pagination' => ['more' => $vendors->hasMorePages()],
        ]);
    }
}
