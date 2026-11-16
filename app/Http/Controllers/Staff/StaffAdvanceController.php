<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DeleteStaffAdvanceRequest;
use App\Http\Requests\Staff\StaffAdvanceRequest;
use App\Models\PaymentMode;
use App\Models\StaffAdvance;
use App\Models\StaffMember;
use App\Services\StaffAdvanceService;
use App\Support\Format;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Staff → Advances: money given before salary, recovered in salary runs (spec Phase 6). */
class StaffAdvanceController extends Controller
{
    public function __construct(private readonly StaffAdvanceService $advances) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'staff_member_id' => $request->integer('staff_member_id') ?: null,
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];

        $query = StaffAdvance::query()
            ->when($filters['staff_member_id'], fn ($q, $id) => $q->where('staff_member_id', $id))
            ->when($filters['from'], fn ($q, $d) => $q->where('advance_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('advance_date', '<=', $d));

        $total = (clone $query)->sum('amount');
        $advances = $query->with(['staffMember:id,name', 'paymentMode:id,name'])
            ->orderByDesc('advance_date')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'staff.advances.index', [
            'advances' => $advances,
            'filters' => $filters,
            'total' => $total,
            'outstanding' => $filters['staff_member_id'] ? ($this->advances->outstanding([$filters['staff_member_id']])[$filters['staff_member_id']] ?? '0.00') : null,
            'staffOptions' => StaffMember::query()->orderByDesc('is_active')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('staff.advances.form', [
            'staffOptions' => StaffMember::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'selected' => old('staff_member_id', $request->integer('staff_member_id') ?: null),
            'modes' => PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(StaffAdvanceRequest $request): RedirectResponse
    {
        [$advance, $created] = $this->advances->record($request->validated());

        return redirect()->route('staff-advances.index', ['staff_member_id' => $advance->staff_member_id])
            ->with('success', $created ? __('Advance of :amount saved.', ['amount' => Format::money($advance->amount)]) : __('This advance was already recorded.'));
    }

    public function destroy(DeleteStaffAdvanceRequest $request, StaffAdvance $staffAdvance): RedirectResponse
    {
        try {
            $this->advances->delete($staffAdvance);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Advance deleted.'));
    }

    private function dateFilter(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
