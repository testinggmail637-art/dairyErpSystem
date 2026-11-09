<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\LoanOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, LoanOverviewService $loans): View
    {
        $user = $request->user();

        return view('dashboard', [
            'data' => $dashboard->data(),
            // loan cards (spec 7.4A): a few grouped queries, not cached so a payment shows at once
            'loans' => $user->can('dashboard.financial') && $user->can('loans.view') ? $loans->overview() : null,
        ]);
    }

    /** Income vs expenses for the period buttons (day / week / month / year) — same rights as the money cards. */
    public function finance(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.financial') && $request->user()->can('expenses.view'), 403);

        $period = $request->validate(['period' => ['required', Rule::in(DashboardService::PERIODS)]])['period'];

        return response()->json($dashboard->finance($period));
    }
}
