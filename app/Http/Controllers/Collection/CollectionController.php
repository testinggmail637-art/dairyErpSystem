<?php

namespace App\Http\Controllers\Collection;

use App\Enums\MilkShift;
use App\Enums\MilkType;
use App\Exceptions\BelowStandardMilkException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CollectionQuoteRequest;
use App\Http\Requests\Collection\CollectionRequest;
use App\Models\Farmer;
use App\Services\CollectionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Collection entry screen (spec Phase 4): date + shift on top, one farmer at a time, live rate and amount, Enter to
 * save; below it the entries of this shift with totals. Saving works with AJAX (JSON) and without (redirect back).
 */
class CollectionController extends Controller
{
    public function __construct(private readonly CollectionService $collections) {}

    public function index(Request $request): View|Response
    {
        $date = Carbon::canBeCreatedFromFormat((string) $request->query('date'), 'Y-m-d') && $request->query('date') <= today()->toDateString()
            ? (string) $request->query('date') : today()->toDateString();
        $shift = MilkShift::tryFrom((string) $request->query('shift')) ?? MilkShift::current();

        return $this->listView($request, 'collection.collections.index', [
            'date' => $date,
            'shift' => $shift,
            'entries' => $this->collections->entries($date, $shift),
            'totals' => $this->collections->shiftTotals($date, $shift),
            'canCreate' => $request->user()->can('collections.create'),
        ]);
    }

    public function store(CollectionRequest $request): JsonResponse|RedirectResponse
    {
        try {
            [$collection, $created] = $this->collections->create($request->validated());
        } catch (BelowStandardMilkException $e) {
            return $this->fail($request, $e->getMessage(), ['below_standard' => true, 'rate' => $e->rate, 'amount' => $e->amount]);
        } catch (DomainException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $collection->load('farmer:id,code,name');
        $message = $created
            ? __(':code :name: :qty L × :rate = :amount saved.', ['code' => $collection->farmer->code, 'name' => $collection->farmer->name,
                'qty' => fmt_qty($collection->quantity_litre), 'rate' => inr($collection->rate_per_litre), 'amount' => inr($collection->amount)])
            : __('This entry was already saved.');

        if ($request->expectsJson()) {
            return response()->json(['id' => $collection->id, 'created' => $created, 'message' => $message], $created ? 201 : 200);
        }

        return redirect()->route('collections.index', ['date' => $collection->collection_date->toDateString(), 'shift' => $collection->shift->value])
            ->with('success', $message);
    }

    /** Live rate and amount for the reading being typed (JSON). */
    public function quote(CollectionQuoteRequest $request): JsonResponse
    {
        $v = $request->validated();

        try {
            $quote = $this->collections->quote(Farmer::query()->findOrFail($v['farmer_id']), MilkType::from($v['milk_type']),
                $v['collection_date'], $v['quantity_litre'] ?? '0', $v['fat'] ?? null, $v['snf'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($quote + ['rate_text' => inr($quote['rate']), 'amount_text' => inr($quote['amount'])]);
    }

    /** @param  array<string, mixed>  $extra */
    private function fail(Request $request, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $extra, 422);
        }

        return back()->withInput()->with('error', $message);
    }
}
