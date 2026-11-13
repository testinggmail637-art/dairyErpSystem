<?php

namespace App\Http\Controllers\Herd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\VaccineTypeRequest;
use App\Models\VaccineType;
use App\Services\VaccinationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Herd → Health → Vaccines: the farm's vaccine list with repeat interval (spec Phase 5). Short list, no paging. */
class VaccineTypeController extends Controller
{
    public function index(Request $request): View
    {
        return view('herd.vaccine-types.index', [
            'types' => VaccineType::query()->withCount('vaccinations')->orderBy('name')->get(),
            'canManage' => $request->user()->can('health.manage'),
        ]);
    }

    public function store(VaccineTypeRequest $request): RedirectResponse
    {
        $type = VaccineType::create($request->validated());

        return back()->with('success', __('Vaccine :name added.', ['name' => $type->name]));
    }

    public function edit(VaccineType $vaccineType): View
    {
        return view('herd.vaccine-types.edit', ['type' => $vaccineType]);
    }

    public function update(VaccineTypeRequest $request, VaccineType $vaccineType): RedirectResponse
    {
        $vaccineType->update($request->validated());

        return redirect()->route('health.vaccine-types.index')->with('success', __('Vaccine :name saved.', ['name' => $vaccineType->name]));
    }

    public function destroy(VaccineType $vaccineType, VaccinationService $vaccinations): RedirectResponse
    {
        try {
            $vaccinations->deleteType($vaccineType);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Vaccine :name deleted.', ['name' => $vaccineType->name]));
    }
}
