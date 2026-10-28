<?php

namespace App\Http\Controllers\MilkStock;

use App\Enums\MilkMovementType;
use App\Enums\MilkSource;
use App\Enums\MilkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\MilkStock\MilkStockMovementRequest;
use App\Services\MilkStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Milk Stock (spec 7.4): daily stock sheet, "Record usage" and "Adjustment" forms, ledger with running balance.
 */
class MilkStockController extends Controller
{
    public function __construct(private readonly MilkStockService $stock) {}

    /** Daily stock sheet: source × milk type rows plus combined, and the day's usage / adjustments. */
    public function index(Request $request): View
    {
        $date = $this->dateFrom($request->query('date'));

        return view('milk-stock.index', [
            'date' => $date,
            'summary' => $this->stock->dailySummary($date),
            'manual' => $this->stock->manualMovementsOn($date),
        ]);
    }

    public function createUsage(Request $request): View
    {
        return $this->form($request, false);
    }

    public function createAdjustment(Request $request): View
    {
        return $this->form($request, true);
    }

    public function store(MilkStockMovementRequest $request): RedirectResponse
    {
        [$movement, $created] = $this->stock->recordManual($request->validated());
        $litres = $movement->movement_type->isIn() ? $movement->quantity_in : $movement->quantity_out;

        return redirect()->route('milk-stock.index', ['date' => $movement->movement_date->toDateString()])
            ->with('success', $created
                ? __(':type saved: :litres L :milk milk, :source.', [
                    'type' => $movement->movement_type->label(),
                    'litres' => fmt_qty($litres),
                    'milk' => mb_strtolower($movement->milk_type->label()),
                    'source' => $movement->stock_source->label(),
                ])
                : __('This entry was already saved.'));
    }

    public function ledger(Request $request): View|Response
    {
        $filters = [
            'from' => $this->validDate($request->query('from')) ?? now()->startOfMonth()->toDateString(),
            'to' => $this->validDate($request->query('to')) ?? today()->toDateString(),
            'source' => MilkSource::tryFrom((string) $request->query('source'))?->value ?? '',
            'milk_type' => MilkType::tryFrom((string) $request->query('milk_type'))?->value ?? '',
            'movement_type' => MilkMovementType::tryFrom((string) $request->query('movement_type'))?->value ?? '',
        ];

        if ($filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        return $this->listView($request, 'milk-stock.ledger', [
            'filters' => $filters,
            'ledger' => $this->stock->ledger($filters, max(1, $request->integer('page', 1)), array_filter($filters)),
        ]);
    }

    private function form(Request $request, bool $adjustment): View
    {
        return view('milk-stock.form', [
            'adjustment' => $adjustment,
            'types' => collect(MilkStockMovementRequest::types($adjustment))
                ->mapWithKeys(fn (MilkMovementType $t) => [$t->value => $t->label()])->all(),
            'date' => $this->dateFrom($request->query('date')),
            'source' => MilkSource::tryFrom((string) $request->query('source'))?->value ?? MilkSource::OwnFarm->value,
            'milkType' => MilkType::tryFrom((string) $request->query('milk_type'))?->value ?? MilkType::Cow->value,
        ]);
    }

    /** ?date= as Y-m-d, never in the future; anything else → today. */
    private function dateFrom(mixed $value): string
    {
        $date = $this->validDate($value);

        return $date && $date <= today()->toDateString() ? $date : today()->toDateString();
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->toDateString() === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
