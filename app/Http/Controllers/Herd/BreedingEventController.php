<?php

namespace App\Http\Controllers\Herd;

use App\Enums\BreedingEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\BreedingEventRequest;
use App\Http\Requests\Herd\DeleteBreedingEventRequest;
use App\Models\Animal;
use App\Models\BreedingEvent;
use App\Models\PaymentMode;
use App\Services\BreedingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Herd → Breeding (spec Phase 5): expected calvings, the event list, add / edit / delete events. */
class BreedingEventController extends Controller
{
    public function __construct(private readonly BreedingService $breeding) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->subDays(180)->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
            'event_type' => BreedingEventType::tryFrom((string) $request->query('event_type'))?->value ?? '',
            'animal_id' => (string) ($request->integer('animal_id') ?: ''),
        ];

        $events = BreedingEvent::query()
            ->whereBetween('event_date', [$filters['from'], $filters['to']])
            ->when($filters['event_type'], fn ($q, $v) => $q->where('event_type', $v))
            ->when($filters['animal_id'], fn ($q, $v) => $q->where('animal_id', $v))
            ->with('animal:id,tag_no,name')
            ->orderByDesc('event_date')->orderByDesc('id')
            ->paginate(50)->appends(array_filter($filters));

        // overdue (up to 30 days late) and the next 90 days, nearest first
        $expected = $this->breeding->expectedCalvings(today()->subDays(30)->toDateString(), today()->addDays(90)->toDateString())
            ->join('animals as a', 'a.id', '=', 'e.animal_id')->whereNull('a.deleted_at')
            ->addSelect(['a.tag_no', 'a.name'])->limit(50)->get();

        $animal = $filters['animal_id'] ? Animal::withTrashed()->find($filters['animal_id'], ['id', 'tag_no', 'name']) : null;

        return $this->listView($request, 'herd.breeding.index', [
            'events' => $events,
            'expected' => $expected,
            'filters' => $filters,
            'types' => BreedingEventType::options(),
            'selectedAnimal' => $animal ? [$animal->id => $animal->label()] : [],
        ]);
    }

    public function create(Request $request): View
    {
        $animal = $request->integer('animal_id') ? Animal::find($request->integer('animal_id'), ['id', 'tag_no', 'name']) : null;
        $type = BreedingEventType::tryFrom((string) $request->query('event_type')) ?? BreedingEventType::Ai;

        return view('herd.breeding.form', $this->formData(new BreedingEvent(['event_date' => today(), 'animal_id' => $animal?->id, 'event_type' => $type]),
            $animal, true, PaymentMode::query()->where('code', 'CASH')->value('id')));
    }

    public function store(BreedingEventRequest $request): RedirectResponse
    {
        $event = $this->breeding->create($request->validated());

        return redirect()->route('breeding.index')->with('success', $event->expected_calving_date
            ? __(':event saved. Expected calving: :date.', ['event' => $event->event_type->label(), 'date' => fmt_date($event->expected_calving_date)])
            : __(':event saved.', ['event' => $event->event_type->label()]));
    }

    public function edit(BreedingEvent $breeding): View
    {
        $breeding->load('animal:id,tag_no,name', 'expense');

        return view('herd.breeding.form', $this->formData($breeding, $breeding->animal, $breeding->expense_id !== null, $breeding->expense?->payment_mode_id));
    }

    public function update(BreedingEventRequest $request, BreedingEvent $breeding): RedirectResponse
    {
        $this->breeding->update($breeding, $request->validated());

        return redirect()->route('breeding.index')->with('success', __(':event saved.', ['event' => $breeding->event_type->label()]));
    }

    public function destroy(DeleteBreedingEventRequest $request, BreedingEvent $breeding): RedirectResponse
    {
        $this->breeding->delete($breeding);

        return back()->with('success', __('Breeding event deleted (with its expense, if any).'));
    }

    /** @return array<string, mixed> */
    private function formData(BreedingEvent $event, ?Animal $animal, bool $recordAsExpense, ?int $paymentModeId): array
    {
        return [
            'event' => $event,
            'animalOptions' => $animal ? [$animal->id => $animal->label()] : [],
            'types' => BreedingEventType::options(),
            'recordAsExpense' => $recordAsExpense,
            'paymentModeId' => $paymentModeId,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
