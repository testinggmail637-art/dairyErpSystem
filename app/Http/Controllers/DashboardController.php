<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        return view('dashboard', ['data' => $dashboard->data()]);
    }

    /** Income vs expenses for the period buttons (day / week / month / year) — same rights as the money cards. */
    public function finance(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.financial') && $request->user()->can('expenses.view'), 403);

        $period = $request->validate(['period' => ['required', Rule::in(DashboardService::PERIODS)]])['period'];

        return response()->json($dashboard->finance($period));
    }
}
