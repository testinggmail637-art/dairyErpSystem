<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StaffMemberRequest;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Staff → Staff members (spec Phase 6). No delete: a worker who leaves gets a leaving date and is marked inactive,
 * so attendance and salary history stay.
 */
class StaffMemberController extends Controller
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => in_array($request->query('status'), ['active', 'inactive', 'all'], true) ? $request->query('status') : '',
        ];

        $staff = StaffMember::query()
            ->with('user:id,name')
            ->search($filters['search'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('is_active', $filters['status'] !== 'inactive'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'staff.members.index', compact('staff', 'filters'));
    }

    public function create(): View
    {
        return $this->form(new StaffMember(['is_active' => true, 'joining_date' => today()]));
    }

    public function store(StaffMemberRequest $request): RedirectResponse
    {
        $member = DB::transaction(fn () => StaffMember::create($request->safe()->except('reason')));

        return redirect()->route('staff.index')->with('success', __('Staff member :name added.', ['name' => $member->name]));
    }

    public function edit(StaffMember $staff): View
    {
        return $this->form($staff);
    }

    public function update(StaffMemberRequest $request, StaffMember $staff): RedirectResponse
    {
        DB::transaction(fn () => $staff->update($request->safe()->except('reason')));

        return redirect()->route('staff.index')->with('success', __('Staff member :name updated.', ['name' => $staff->name]));
    }

    /** Login users of this farm that are not linked to another staff member. */
    private function form(StaffMember $member): View
    {
        $taken = StaffMember::query()->whereNotNull('user_id')->when($member->exists, fn ($q) => $q->whereKeyNot($member->id))->pluck('user_id');

        return view('staff.members.form', [
            'member' => $member,
            'users' => User::query()->whereHas('farms', fn ($q) => $q->whereKey($this->currentFarm->id()))
                ->whereNotIn('id', $taken)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
