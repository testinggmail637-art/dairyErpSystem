<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\SalaryRunStatus;
use App\Models\StaffMember;
use App\Reports\ReportTable;
use App\Support\CurrentFarm;
use App\Support\Format;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Staff registers (spec 7.6 #11): monthly attendance register and salary register. */
class StaffReportService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** One row per staff member employed in the month: each day's code, then the day counts and paid days. */
    public function attendance(string $month): ReportTable
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = $start->daysInMonth;
        $paidLeave = (bool) $this->currentFarm->farm()?->setting('salary_paid_leave', false);

        $staff = StaffMember::query()
            ->where('joining_date', '<=', $end->toDateString())
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->whereNull('leaving_date'))
                ->orWhere('leaving_date', '>=', $start->toDateString()))
            ->orderBy('name')->get(['id', 'name']);

        $marks = [];
        DB::table('attendances')->whereIn('staff_member_id', $staff->pluck('id')->all())
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get(['staff_member_id', 'attendance_date', 'status'])
            ->each(function ($r) use (&$marks) {
                $marks[$r->staff_member_id][(int) substr($r->attendance_date, 8, 2)] = AttendanceStatus::from($r->status);
            });

        $rows = $staff->map(function (StaffMember $s) use ($marks, $days, $paidLeave) {
            $m = $marks[$s->id] ?? [];
            $count = fn (AttendanceStatus $st) => count(array_filter($m, fn ($v) => $v === $st));
            $paid = $count(AttendanceStatus::Present) + $count(AttendanceStatus::Holiday) + ($paidLeave ? $count(AttendanceStatus::Leave) : 0)
                + $count(AttendanceStatus::HalfDay) / 2;

            return [
                $s->name,
                ...array_map(fn ($d) => isset($m[$d]) ? $m[$d]->short() : '', range(1, $days)),
                $count(AttendanceStatus::Present), $count(AttendanceStatus::HalfDay), $count(AttendanceStatus::Leave),
                $count(AttendanceStatus::Holiday), $count(AttendanceStatus::Absent),
                rtrim(rtrim(number_format($paid, 1, '.', ''), '0'), '.'),
            ];
        });

        return new ReportTable(
            __('Attendance register'),
            $start->translatedFormat('F Y'),
            [
                ReportTable::column(__('Staff member')),
                ...array_map(fn ($d) => ReportTable::column((string) $d), range(1, $days)),
                ReportTable::column('P', ReportTable::NUMBER), ReportTable::column('H', ReportTable::NUMBER), ReportTable::column('L', ReportTable::NUMBER),
                ReportTable::column('HO', ReportTable::NUMBER), ReportTable::column('A', ReportTable::NUMBER), ReportTable::column(__('Paid days')),
            ],
            $rows->all(),
            null,
            [
                __('P present · H half day · L leave · HO holiday · A absent · blank not marked (unpaid).'),
                $paidLeave ? __('Paid days = P + HO + L + ½ × H.') : __('Paid days = P + HO + ½ × H (leave is unpaid).'),
            ],
            emptyText: __('No staff employed in this month.'),
        );
    }

    /** Finalized salary slips of the months in the period, with totals. */
    public function salary(string $from, string $to): ReportTable
    {
        $fromMonth = Carbon::parse($from)->startOfMonth()->toDateString();
        $toMonth = Carbon::parse($to)->startOfMonth()->toDateString();

        $slips = DB::table('salary_slips as s')
            ->join('salary_runs as r', 'r.id', '=', 's.salary_run_id')
            ->join('staff_members as m', 'm.id', '=', 's.staff_member_id')
            ->leftJoin('payment_modes as pm', 'pm.id', '=', 's.payment_mode_id')
            ->where('r.farm_id', $this->currentFarm->id())
            ->where('r.status', SalaryRunStatus::Finalized->value)
            ->whereBetween('r.month', [$fromMonth, $toMonth])
            ->orderBy('r.month')->orderBy('m.name')
            ->get(['r.month', 'm.name', 'm.designation', 's.payable_days', 's.days_in_month', 's.base_salary', 's.earned_salary', 's.bonus',
                's.advance_deduction', 's.other_deduction', 's.net_salary', 's.paid_on', 'pm.name as mode']);

        $rows = $slips->map(fn ($s) => [
            Carbon::parse($s->month)->translatedFormat('M Y'), $s->name, $s->designation,
            rtrim(rtrim((string) $s->payable_days, '0'), '.').' / '.$s->days_in_month,
            Money::round2((string) $s->base_salary), Money::round2((string) $s->earned_salary), Money::round2((string) $s->bonus),
            Money::round2((string) $s->advance_deduction), Money::round2((string) $s->other_deduction), Money::round2((string) $s->net_salary),
            $s->paid_on ? Format::date($s->paid_on).($s->mode ? ' · '.$s->mode : '') : __('Pending'),
        ]);

        $sum = fn (int $i) => Money::sum($rows->pluck($i));

        return new ReportTable(
            __('Salary register'),
            Format::date($fromMonth).' – '.Format::date(Carbon::parse($toMonth)->endOfMonth()),
            [
                ReportTable::column(__('Month')), ReportTable::column(__('Staff member')), ReportTable::column(__('Designation')),
                ReportTable::column(__('Paid days')), ReportTable::column(__('Monthly salary'), ReportTable::MONEY),
                ReportTable::column(__('Earned'), ReportTable::MONEY), ReportTable::column(__('Bonus'), ReportTable::MONEY),
                ReportTable::column(__('Advance'), ReportTable::MONEY), ReportTable::column(__('Other ded.'), ReportTable::MONEY),
                ReportTable::column(__('Net'), ReportTable::MONEY), ReportTable::column(__('Paid')),
            ],
            $rows->all(),
            [__('Total'), '', '', '', null, $sum(5), $sum(6), $sum(7), $sum(8), $sum(9), ''],
            [__('Finalized salary runs only; drafts are not included.')],
            emptyText: __('No finalized salary runs in this period.'),
        );
    }
}
