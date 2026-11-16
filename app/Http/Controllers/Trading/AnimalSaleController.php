<?php

namespace App\Http\Controllers\Trading;

use App\Enums\AnimalSaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trading\AnimalSaleRequest;
use App\Http\Requests\Trading\CancelAnimalSaleRequest;
use App\Models\Animal;
use App\Models\AnimalSale;
use App\Models\Party;
use App\Services\AnimalSaleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Trading → Sales: sell active animals to a party; cancel with a reason (spec Phase 6). */
class AnimalSaleController extends Controller
{
    public function __construct(private readonly AnimalSaleService $sales) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
            'status' => AnimalSaleStatus::tryFrom((string) $request->query('status'))?->value ?? '',
        ];

        $sales = AnimalSale::query()
            ->with('party:id,name,village')
            ->withCount('items')
            ->search($filters['search'])
            ->when($filters['from'], fn ($q, $d) => $q->where('sale_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('sale_date', '<=', $d))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('sale_date')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'trading.sales.index', compact('sales', 'filters'));
    }

    public function create(Request $request): View
    {
        $party = old('party_id') ? Party::query()->whereKey(old('party_id'))->pluck('name', 'id')->all() : [];
        // labels of animals already chosen (after a validation error, or "Sell" from an animal's page)
        $ids = collect(old('animals', []))->pluck('animal_id')->push($request->integer('animal_id'))->filter()->unique();
        $animalLabels = Animal::query()->whereKey($ids)->get(['id', 'tag_no', 'name'])->mapWithKeys(fn ($a) => [$a->id => $a->label()]);

        return view('trading.sales.form', [
            'selectedParty' => $party,
            'animalLabels' => $animalLabels,
            'rows' => old('animals') ?: [['animal_id' => $request->integer('animal_id') ?: null, 'price' => null]],
        ]);
    }

    public function store(AnimalSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->sales->create($request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('animal-sales.show', $sale)->with('success', __('Sale :no saved.', ['no' => $sale->sale_no]));
    }

    public function show(AnimalSale $animalSale): View
    {
        $animalSale->load(['party', 'items.animal.breed:id,name', 'creator:id,name', 'canceller:id,name', 'payments.paymentMode:id,name']);

        return view('trading.sales.show', ['sale' => $animalSale]);
    }

    public function cancel(CancelAnimalSaleRequest $request, AnimalSale $animalSale): RedirectResponse
    {
        try {
            $this->sales->cancel($animalSale);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('animal-sales.show', $animalSale)->with('success', __('Sale :no cancelled; its animals are active again.', ['no' => $animalSale->sale_no]));
    }

    private function dateFilter(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
