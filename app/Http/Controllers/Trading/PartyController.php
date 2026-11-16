<?php

namespace App\Http\Controllers\Trading;

use App\Enums\PartyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trading\PartyRequest;
use App\Models\Party;
use App\Services\PartyService;
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
        $party->delete();

        return redirect()->route('parties.index')->with('success', __('Party :name deleted.', ['name' => $party->name]));
    }
}
