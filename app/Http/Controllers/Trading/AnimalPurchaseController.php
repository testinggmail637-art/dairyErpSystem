<?php

namespace App\Http\Controllers\Trading;

use App\Enums\AnimalGender;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trading\AnimalPurchaseRequest;
use App\Http\Requests\Trading\DeleteAnimalPurchaseRequest;
use App\Models\AnimalBreed;
use App\Models\AnimalPurchase;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Services\AnimalPurchaseService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Trading → Purchases: buy animals from a party, creating the animals inline (spec Phase 6). */
class AnimalPurchaseController extends Controller
{
    public function __construct(private readonly AnimalPurchaseService $purchases) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];

        $purchases = AnimalPurchase::query()
            ->with('party:id,name,village')
            ->withCount('items')
            ->search($filters['search'])
            ->when($filters['from'], fn ($q, $d) => $q->where('purchase_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('purchase_date', '<=', $d))
            ->orderByDesc('purchase_date')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'trading.purchases.index', compact('purchases', 'filters'));
    }

    public function create(Request $request): View
    {
        $party = old('party_id') ? Party::query()->whereKey(old('party_id'))->pluck('name', 'id')->all() : [];

        return view('trading.purchases.form', [
            'selectedParty' => $party,
            'breeds' => AnimalBreed::query()->active()->orderBy('name')->get(['id', 'name', 'animal_type']),
            'stagesByGender' => collect(AnimalGender::cases())->mapWithKeys(fn ($g) => [
                $g->value => collect(LifecycleStage::forGender($g))->map(fn ($s) => $s->value)->all(),
            ]),
            'ownershipOptions' => OwnershipType::options(),
            'modes' => PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(AnimalPurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->validated());

        return redirect()->route('animal-purchases.show', $purchase)
            ->with('success', __('Purchase :no saved with :count animal(s).', ['no' => $purchase->purchase_no, 'count' => $purchase->items()->count()]));
    }

    public function show(AnimalPurchase $animalPurchase): View
    {
        $animalPurchase->load(['party', 'items.animal.breed:id,name', 'creator:id,name', 'payments.paymentMode:id,name']);

        return view('trading.purchases.show', ['purchase' => $animalPurchase]);
    }

    public function destroy(DeleteAnimalPurchaseRequest $request, AnimalPurchase $animalPurchase): RedirectResponse
    {
        try {
            $this->purchases->delete($animalPurchase);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('animal-purchases.index')
            ->with('success', __('Purchase :no deleted.', ['no' => $animalPurchase->purchase_no]));
    }

    private function dateFilter(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
