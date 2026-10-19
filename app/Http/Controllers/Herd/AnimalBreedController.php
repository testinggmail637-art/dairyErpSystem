<?php

namespace App\Http\Controllers\Herd;

use App\Enums\AnimalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\AnimalBreedRequest;
use App\Models\AnimalBreed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Herd → Breeds (spec Section 6, Phase 2): cow and buffalo breeds of the farm. No delete — a breed is
 * deactivated so animals keep it, and inactive breeds are not offered for new animals.
 */
class AnimalBreedController extends Controller
{
    public function index(Request $request): View|Response
    {
        $filters = [
            'type' => AnimalType::tryFrom((string) $request->query('type'))?->value ?? '',
            'status' => in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '',
            'search' => mb_substr(trim((string) $request->query('search')), 0, 60),
        ];

        $breeds = AnimalBreed::query()
            ->when($filters['type'], fn ($q, $type) => $q->where('animal_type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->when($filters['search'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%'))
            ->orderBy('animal_type')
            ->orderBy('name')
            ->paginate(50)
            ->appends(array_filter($filters));

        return $this->listView($request, 'herd.breeds.index', [
            'breeds' => $breeds,
            'filters' => $filters,
            'canManage' => $request->user()->can('animals.manage'),
        ]);
    }

    /** Active breeds of one type as {"id": "name"} — the animal form's breed list follows the chosen type. */
    public function byType(AnimalType $type): JsonResponse
    {
        return response()->json(AnimalBreed::query()->active()->ofType($type)->orderBy('name')->pluck('name', 'id'));
    }

    public function create(Request $request): View
    {
        return view('herd.breeds.form', ['breed' => new AnimalBreed([
            'animal_type' => AnimalType::tryFrom((string) $request->query('type')) ?? AnimalType::Cow,
            'is_active' => true,
        ])]);
    }

    public function store(AnimalBreedRequest $request): RedirectResponse
    {
        $breed = AnimalBreed::create($request->validated());

        return redirect()->route('animal-breeds.index', ['type' => $breed->animal_type->value])
            ->with('success', __('Breed ":name" added.', ['name' => $breed->name]));
    }

    public function edit(AnimalBreed $animalBreed): View
    {
        return view('herd.breeds.form', ['breed' => $animalBreed]);
    }

    public function update(AnimalBreedRequest $request, AnimalBreed $animalBreed): RedirectResponse
    {
        $animalBreed->update($request->validated());

        return redirect()->route('animal-breeds.index', ['type' => $animalBreed->animal_type->value])
            ->with('success', __('Breed ":name" updated.', ['name' => $animalBreed->name]));
    }
}
