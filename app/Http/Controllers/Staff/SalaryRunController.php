<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PaySalarySlipRequest;
use App\Http\Requests\Staff\SalaryRunRequest;
use App\Http\Requests\Staff\SalarySlipRequest;
use App\Models\PaymentMode;
use App\Models\SalaryRun;
use App\Models\SalarySlip;
use App\Services\SalaryRunService;
use App\Services\StaffAdvanceService;
use App\Support\CurrentFarm;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Staff → Salary runs: draft → edit slips → finalize (expenses) → mark paid; salary slip PDF (spec Phase 6). */
class SalaryRunController extends Controller
{
    public function __construct(private readonly SalaryRunService $runs) {}

    public function index(Request $request): View|Response
    {
        $runs = SalaryRun::query()
            ->withCount('slips')
            ->withSum('slips as net_total', 'net_salary')
            ->withSum('slips as earned_total', 'earned_salary')
            ->orderByDesc('month')
            ->paginate(24)
            ->withQueryString();

        return $this->listView($request, 'staff.salary.index', compact('runs'));
    }

    public function store(SalaryRunRequest $request): RedirectResponse
    {
        try {
            $run = $this->runs->create($request->validated('month'));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('salary-runs.show', $run)->with('success', __('Draft salary run for :month created.', ['month' => $run->label()]));
    }

    public function show(SalaryRun $salaryRun): View
    {
        $salaryRun->load(['slips' => fn ($q) => $q->with(['staffMember:id,name,designation', 'paymentMode:id,name'])
            ->join('staff_members', 'staff_members.id', '=', 'salary_slips.staff_member_id')->orderBy('staff_members.name')->select('salary_slips.*'),
            'finalizer:id,name']);

        return view('staff.salary.show', [
            'run' => $salaryRun,
            'modes' => PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'paidLeave' => (bool) app(CurrentFarm::class)->farm()?->setting('salary_paid_leave', false),
        ]);
    }

    public function recalculate(SalaryRun $salaryRun): RedirectResponse
    {
        return $this->act(fn () => $this->runs->recalculate($salaryRun), $salaryRun, __('Slips recalculated from attendance.'));
    }

    public function finalize(SalaryRun $salaryRun): RedirectResponse
    {
        return $this->act(fn () => $this->runs->finalize($salaryRun), $salaryRun, __('Salary run finalized; salary expenses created.'));
    }

    public function destroy(SalaryRun $salaryRun): RedirectResponse
    {
        try {
            $this->runs->delete($salaryRun);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('salary-runs.index')->with('success', __('Draft salary run deleted.'));
    }

    public function editSlip(SalarySlip $salarySlip): View|RedirectResponse
    {
        if ($salarySlip->run->isFinalized()) {
            return redirect()->route('salary-runs.show', $salarySlip->salary_run_id)->with('error', __('This salary run is finalized and can no longer be changed.'));
        }

        $salarySlip->load('staffMember:id,name,designation');

        return view('staff.salary.slip-form', [
            'slip' => $salarySlip,
            'outstanding' => app(StaffAdvanceService::class)->outstanding([$salarySlip->staff_member_id])[$salarySlip->staff_member_id],
        ]);
    }

    public function updateSlip(SalarySlipRequest $request, SalarySlip $salarySlip): RedirectResponse
    {
        try {
            $this->runs->updateSlip($salarySlip, $request->safe()->except('reason'));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('salary-runs.show', $salarySlip->salary_run_id)->with('success', __('Slip of :name updated.', ['name' => $salarySlip->staffMember->name]));
    }

    public function paySlip(PaySalarySlipRequest $request, SalarySlip $salarySlip): RedirectResponse
    {
        return $this->act(fn () => $this->runs->pay($salarySlip, $request->validated('paid_on'), (int) $request->validated('payment_mode_id')),
            $salarySlip->run, __('Salary of :name marked paid.', ['name' => $salarySlip->staffMember->name]));
    }

    public function slipPdf(SalarySlip $salarySlip): Response
    {
        $salarySlip->load(['run', 'staffMember', 'paymentMode:id,name']);

        return Pdf::loadView('pdf.salary-slip', ['slip' => $salarySlip])
            ->stream(str_replace([' ', '/'], '-', 'salary-'.$salarySlip->run->month->format('Y-m').'-'.$salarySlip->staffMember->name).'.pdf');
    }

    private function act(callable $action, SalaryRun $run, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return redirect()->route('salary-runs.show', $run)->with('error', $e->getMessage());
        }

        return redirect()->route('salary-runs.show', $run)->with('success', $message);
    }
}
