<?php

namespace App\Http\Controllers\Herd;

use App\Enums\AnimalGender;
use App\Enums\AnimalSource;
use App\Enums\AnimalStatus;
use App\Enums\AnimalType;
use App\Enums\LifecycleStage;
use App\Enums\OwnershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\AnimalChangeRequest;
use App\Http\Requests\Herd\AnimalRequest;
use App\Http\Requests\Herd\DeleteAnimalRequest;
use App\Models\Activity;
use App\Models\Animal;
use App\Models\AnimalBreed;
use App\Models\Expense;
use App\Services\AnimalService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Herd → Animals (spec Section 6, Phase 2): list with filters, profile with tabs, add/edit with photo, soft delete.
 */
class AnimalController extends Controller
{
    public function __construct(private readonly AnimalService $animals) {}

    public function index(Request $request): View|Response
    {
        $filters = $this->filters($request);

        $animals = $this->animals->applyFilters(Animal::query(), $filters)
            ->select(['id', 'tag_no', 'name', 'animal_type', 'breed_id', 'gender', 'date_of_birth', 'dob_is_estimated',
                'ownership_type', 'lifecycle_stage', 'status', 'lactation_number', 'photo_path'])
            ->with('breed:id,name')
            ->orderBy('tag_no')
            ->paginate(50)
            ->appends(array_filter($filters));

        return $this->listView($request, 'herd.animals.index', [
            'animals' => $animals,
            'filters' => $filters,
            'stageCounts' => $this->animals->stageCounts(),
            'breeds' => AnimalBreed::query()->orderBy('animal_type')->orderBy('name')->get(['id', 'name', 'animal_type'])
                ->mapWithKeys(fn ($b) => [$b->id => $b->name.' ('.$b->animal_type->label().')'])->all(),
            'canManage' => $request->user()->can('animals.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $type = AnimalType::tryFrom((string) $request->query('type')) ?? AnimalType::Cow;

        return view('herd.animals.form', $this->formData(new Animal([
            'animal_type' => $type,
            'gender' => AnimalGender::Female,
            'source' => AnimalSource::Opening,
            'ownership_type' => OwnershipType::OwnHerd,
            'lifecycle_stage' => LifecycleStage::Milking,
            'lactation_number' => 0,
        ])));
    }

    public function store(AnimalRequest $request): RedirectResponse
    {
        $animal = $this->animals->create($request->animalData(), $request->file('photo'));

        if ($request->boolean('add_another')) {
            return redirect()->route('animals.create', ['type' => $animal->animal_type->value])
                ->with('success', __('Animal :tag added. Add the next one.', ['tag' => $animal->tag_no]));
        }

        return redirect()->route('animals.show', $animal)->with('success', __('Animal :tag added.', ['tag' => $animal->tag_no]));
    }

    /** Profile: details, milk, health, breeding, expenses and history tabs. */
    public function show(Animal $animal): View
    {
        $animal->load(['breed:id,name', 'mother:id,tag_no,name', 'creator:id,name']);

        $expenses = Expense::query()->where('animal_id', $animal->id);

        return view('herd.animals.show', [
            'animal' => $animal,
            'calves' => $animal->calves()->orderByDesc('date_of_birth')
                ->get(['id', 'tag_no', 'name', 'gender', 'date_of_birth', 'dob_is_estimated', 'lifecycle_stage', 'status']),
            'expenseTotal' => (clone $expenses)->sum('amount'),
            'expenseCount' => (clone $expenses)->count(),
            'recentExpenses' => (clone $expenses)->with(['category:id,name', 'subcategory:id,name'])
                ->latest('expense_date')->latest('id')->limit(10)->get(),
            'statusHistory' => $animal->statusHistories()->with('changer:id,name')
                ->latest('changed_on')->latest('id')->limit(50)->get(),
            'history' => Activity::query()
                ->where('subject_type', $animal->getMorphClass())->where('subject_id', $animal->id)
                ->with('causer:id,name')->latest('id')->limit(30)->get(),
        ]);
    }

    public function edit(Animal $animal): View
    {
        return view('herd.animals.form', $this->formData($animal->load('mother:id,tag_no,name')));
    }

    public function update(AnimalRequest $request, Animal $animal): RedirectResponse
    {
        $this->animals->update($animal, $request->animalData(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('animals.show', $animal)->with('success', __('Animal :tag updated.', ['tag' => $animal->tag_no]));
    }

    /** Status / stage / ownership action from the profile page (history written by AnimalObserver). */
    public function change(AnimalChangeRequest $request, Animal $animal): RedirectResponse
    {
        $field = $request->validated('field');
        $this->animals->change($animal, $field, $request->validated('value'), $request->validated('changed_on'));

        $label = match ($field) {
            'status' => __('Status'),
            'lifecycle_stage' => __('Stage'),
            default => __('Ownership'),
        };

        return redirect()->route('animals.show', $animal)->with('success', __(':field of :tag changed.', ['field' => $label, 'tag' => $animal->tag_no]));
    }

    public function destroy(DeleteAnimalRequest $request, Animal $animal): RedirectResponse
    {
        try {
            $this->animals->delete($animal);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('animals.index')->with('success', __('Animal :tag deleted.', ['tag' => $animal->tag_no]));
    }

    /**
     * Select2 remote search: ?q=…&type=cow&gender=female&except=5 → {"results": [{id, text}], "pagination": {more}}.
     * Used for the mother field (and later for animal pickers on other forms).
     */
    public function search(Request $request): JsonResponse
    {
        $animals = Animal::query()
            ->search($request->query('q'))
            ->when(AnimalType::tryFrom((string) $request->query('type')), fn ($q, $type) => $q->where('animal_type', $type->value))
            ->when(AnimalGender::tryFrom((string) $request->query('gender')), fn ($q, $gender) => $q->where('gender', $gender->value))
            ->when($request->integer('except'), fn ($q, $id) => $q->whereKeyNot($id))
            ->when($request->boolean('active'), fn ($q) => $q->where('status', AnimalStatus::Active->value))
            ->orderBy('tag_no')
            ->simplePaginate(20, ['id', 'tag_no', 'name', 'status'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'results' => $animals->getCollection()->map(fn (Animal $a) => [
                'id' => $a->id,
                'text' => $a->label().($a->status === AnimalStatus::Active ? '' : ' ('.$a->status->label().')'),
            ])->values(),
            'pagination' => ['more' => $animals->hasMorePages()],
        ]);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status');

        return [
            'type' => AnimalType::tryFrom((string) $request->query('type'))?->value ?? '',
            'stage' => LifecycleStage::tryFrom((string) $request->query('stage'))?->value ?? '',
            'status' => $status === 'all' || AnimalStatus::tryFrom($status) ? $status : '',
            'ownership' => OwnershipType::tryFrom((string) $request->query('ownership'))?->value ?? '',
            'gender' => AnimalGender::tryFrom((string) $request->query('gender'))?->value ?? '',
            'breed_id' => (string) ($request->integer('breed_id') ?: ''),
            'search' => mb_substr(trim((string) $request->query('search')), 0, 60),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(Animal $animal): array
    {
        // after a failed save the form shows what was typed (type, mother) — keep the dropdowns in step with it
        $type = AnimalType::tryFrom((string) old('animal_type')) ?? $animal->animal_type ?? AnimalType::Cow;
        $motherId = (int) old('mother_id', $animal->mother_id);
        $mother = $motherId ? Animal::query()->find($motherId, ['id', 'tag_no', 'name']) : null;

        return [
            'animal' => $animal,
            'breeds' => AnimalBreed::query()->ofType($type)
                ->where(fn ($q) => $q->where('is_active', true)->when($animal->breed_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id')->all(),
            'mother' => $mother ? [$mother->id => $mother->label()] : [],
        ];
    }
}
