<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateFarmSettingsRequest;
use App\Services\FarmSettingsService;
use App\Support\CurrentFarm;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Settings → Farm: details and settings of the current farm.
 */
class FarmSettingsController extends Controller
{
    public function edit(CurrentFarm $currentFarm): View
    {
        $farm = $currentFarm->farm();

        return view('settings.farm.edit', ['farm' => $farm, 'settings' => $farm->allSettings()]);
    }

    public function update(UpdateFarmSettingsRequest $request, CurrentFarm $currentFarm, FarmSettingsService $service): RedirectResponse
    {
        $service->update($currentFarm->farm(), $request->validated());

        return redirect()->route('settings.farm.edit')->with('success', __('Farm settings saved.'));
    }
}
