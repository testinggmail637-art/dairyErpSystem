<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\SwitchFarmRequest;
use App\Models\Farm;
use App\Support\CurrentFarm;
use Illuminate\Http\RedirectResponse;

class FarmSwitchController extends Controller
{
    public function __invoke(SwitchFarmRequest $request, CurrentFarm $currentFarm): RedirectResponse
    {
        $farm = Farm::query()->findOrFail($request->integer('farm_id'));

        $currentFarm->set($farm);

        // Back to the dashboard: the previous page may show a record of the old farm.
        return redirect()->route('dashboard')
            ->with('success', __('Switched to :farm.', ['farm' => $farm->name]));
    }
}
