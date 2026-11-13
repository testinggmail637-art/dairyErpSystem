<?php

namespace App\Http\Controllers\Herd;

use App\Enums\AnimalGender;
use App\Enums\CalvingOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\CalvingRequest;
use App\Http\Requests\Herd\DeleteCalvingRequest;
use App\Models\Animal;
use App\Models\Calving;
use App\Services\CalvingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Herd → Breeding → Calvings (spec Phase 5): list, record a calving (calf, lactation, stage), undo. */
class CalvingController extends Controller
{
    public function __construct(private readonly CalvingService $calvings) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->subYear()->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
        ];

        $calvings = Calving::query()->whereBetween('calving_date', [$filters['from'], $filters['to']])
            ->with(['mother:id,tag_no,name', 'calf:id,tag_no,name'])
            ->orderByDesc('calving_date')->orderByDesc('id')
            ->paginate(50)->appends($filters);

        return $this->listView($request, 'herd.breeding.calvings', ['calvings' => $calvings, 'filters' => $filters]);
    }

    public function create(Request $request): View
    {
        $mother = $request->integer('mother_id') ? Animal::find($request->integer('mother_id'), ['id', 'tag_no', 'name']) : null;

        return view('herd.breeding.calving-form', [
            'motherOptions' => $mother ? [$mother->id => $mother->label()] : [],
            'motherId' => $mother?->id,
            'outcomes' => CalvingOutcome::options(),
            'genders' => AnimalGender::options(),
        ]);
    }

    public function store(CalvingRequest $request): RedirectResponse
    {
        $calving = $this->calvings->create($request->validated())->load('mother:id,tag_no,name,lactation_number', 'calf:id,tag_no');

        return redirect()->route('breeding.calvings.index')->with('success', $calving->calf
            ? __('Calving saved: :mother is in lactation :n, calf :calf added to the herd.', ['mother' => $calving->mother->tag_no, 'n' => $calving->mother->lactation_number, 'calf' => $calving->calf->tag_no])
            : __('Calving saved: :mother is in lactation :n.', ['mother' => $calving->mother->tag_no, 'n' => $calving->mother->lactation_number]));
    }

    public function destroy(DeleteCalvingRequest $request, Calving $calving): RedirectResponse
    {
        try {
            $this->calvings->delete($calving);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Calving removed; the lactation number went back by one.'));
    }
}
