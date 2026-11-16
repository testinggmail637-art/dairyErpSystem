<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AttendanceRequest;
use App\Services\AttendanceService;
use App\Services\SalaryRunService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff → Attendance: pick a date, mark every staff member at once (spec Phase 6). */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $date = (string) $request->query('date');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= today()->toDateString() ? $date : today()->toDateString();

        return view('staff.attendance.index', [
            'date' => $date,
            'staff' => $this->attendance->staffOn($date),
            'rows' => $this->attendance->rowsOn($date),
            'locked' => app(SalaryRunService::class)->isMonthLocked($date),
        ]);
    }

    public function store(AttendanceRequest $request): RedirectResponse
    {
        $date = $request->validated('date');

        try {
            $saved = $this->attendance->saveDay($date, $request->validated('attendance', []));
        } catch (DomainException $e) {
            return redirect()->route('attendance.index', ['date' => $date])->with('error', $e->getMessage());
        }

        return redirect()->route('attendance.index', ['date' => $date])
            ->with('success', __('Attendance for :date saved (:n marked).', ['date' => fmt_date($date), 'n' => $saved]));
    }
}
