<?php

namespace App\Http\Controllers\Sales;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\GenerateDeliveriesRequest;
use App\Models\Delivery;
use App\Models\DeliveryRoute;
use App\Services\DeliverySheetService;
use App\Support\CurrentFarm;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sales → Deliveries: sheet summary per route and shift for a date, and "Generate sheet" (spec Section 6). */
class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $date = Carbon::canBeCreatedFromFormat((string) $request->query('date'), 'Y-m-d')
            ? (string) $request->query('date') : now()->toDateString();
        $user = $request->user();
        $allRoutes = $user->can('deliveries.view_all_routes');

        $rows = Delivery::query()
            ->where('delivery_date', $date)
            ->unless($allRoutes, fn ($q) => $q->whereIn('route_id', DeliveryRoute::query()->where('delivery_user_id', $user->id)->select('id')))
            ->groupBy('route_id', 'shift')
            ->selectRaw('route_id, shift, COUNT(*) as total, SUM(planned_qty) as planned_qty,
                SUM(status = ?) as pending, SUM(status = ?) as delivered, SUM(status = ?) as skipped, SUM(delivered_qty) as delivered_qty',
                [DeliveryStatus::Pending->value, DeliveryStatus::Delivered->value, DeliveryStatus::Skipped->value])
            ->orderBy('shift')
            ->get();

        return view('sales.deliveries.index', [
            'date' => $date,
            'rows' => $rows,
            'routes' => DeliveryRoute::query()->whereIn('id', $rows->pluck('route_id')->filter())->pluck('name', 'id'),
            'canGenerate' => $allRoutes,
        ]);
    }

    public function generate(GenerateDeliveriesRequest $request, DeliverySheetService $sheets, CurrentFarm $farm): RedirectResponse
    {
        $date = $request->validated('date');
        $count = $sheets->generate($farm->farm(), $date);

        return redirect()->route('deliveries.index', ['date' => $date])->with('success', $count > 0
            ? __(':count new deliveries added for :date.', ['count' => $count, 'date' => fmt_date($date)])
            : __('The sheet for :date is already up to date.', ['date' => fmt_date($date)]));
    }
}
