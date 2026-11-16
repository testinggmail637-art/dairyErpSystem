<?php

namespace App\Http\Controllers\Trading;

use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trading\PartyRequest;
use App\Models\AnimalPurchase;
use App\Models\Party;
use App\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Trading → Parties: animal traders we buy from / sell to (spec Phase 6). */
class PartyController extends Controller
{
    public function __construct(private readonly PartyService $parties) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'type' => PartyType::tryFrom((string) $request->query('type'))?->value ?? '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
        ];

        $parties = Party::query()
            ->search($filters['search'])
            ->when($filters['type'], fn ($q, $type) => $q->where('party_type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'trading.parties.index', compact('parties', 'filters'));
    }

    /** Select2 remote search: active parties, `type=seller|buyer` keeps those who sell / buy (plus "both"). */
    public function search(Request $request): JsonResponse
    {
        $type = PartyType::tryFrom((string) $request->query('type'));

        $parties = Party::query()
            ->where('is_active', true)
            ->when($type && $type !== PartyType::Both, fn ($q) => $q->whereIn('party_type', [$type->value, PartyType::Both->value]))
            ->search($request->query('q'))
            ->orderBy('name')
            ->simplePaginate(20, ['id', 'name', 'village'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'results' => $parties->getCollection()->map(fn (Party $p) => [
                'id' => $p->id,
                'text' => $p->village ? "{$p->name} · {$p->village}" : $p->name,
            ])->values(),
            'pagination' => ['more' => $parties->hasMorePages()],
        ]);
    }

    public function create(): View
    {
        return view('trading.parties.form', ['party' => new Party(['party_type' => PartyType::Both, 'is_active' => true, 'opening_balance' => '0.00'])]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $party = $this->parties->create($request->validated());

        return redirect()->route('parties.index')->with('success', __('Party :name added.', ['name' => $party->name]));
    }

    public function edit(Party $party): View
    {
        return view('trading.parties.form', ['party' => $party]);
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        $this->parties->update($party, $request->validated());

        return redirect()->route('parties.index')->with('success', __('Party :name updated.', ['name' => $party->name]));
    }

    public function destroy(Party $party): RedirectResponse
    {
        if (AnimalPurchase::query()->where('party_id', $party->id)->exists()) {
            return back()->with('error', __('Party :name has purchases and cannot be deleted. Mark it inactive instead.', ['name' => $party->name]));
        }

        $party->delete();

        return redirect()->route('parties.index')->with('success', __('Party :name deleted.', ['name' => $party->name]));
    }
}
