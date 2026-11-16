<?php

namespace App\Services;

use App\Enums\SalaryRunStatus;
use App\Models\StaffAdvance;
use App\Support\Money;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Staff advances (spec Phase 6). Idempotent on client_uuid (spec 4.7). Advances are not expenses. */
class StaffAdvanceService
{
    /**
     * @param  array<string, mixed>  $data  validated fields
     * @return array{0: StaffAdvance, 1: bool} the advance and whether it was newly created
     */
    public function record(array $data): array
    {
        $uuid = $data['client_uuid'] ?? null;

        if ($uuid && ($existing = StaffAdvance::query()->where('client_uuid', $uuid)->first())) {
            return [$existing, false];
        }

        try {
            return [DB::transaction(fn () => StaffAdvance::create($data)), true];
        } catch (UniqueConstraintViolationException $e) {
            $existing = $uuid ? StaffAdvance::query()->where('client_uuid', $uuid)->first() : null;

            return $existing ? [$existing, false] : throw $e;
        }
    }

    /**
     * Wrong entries are deleted with a reason (audit-logged) and entered again — not once salary has already recovered
     * part of it (the outstanding would go below zero).
     */
    public function delete(StaffAdvance $advance): void
    {
        $outstanding = $this->outstanding([$advance->staff_member_id])[$advance->staff_member_id];

        if (Money::isNegative(Money::sub($outstanding, $advance->amount))) {
            throw new DomainException(__('This advance has already been recovered through a finalized salary run and cannot be deleted.'));
        }

        DB::transaction(fn () => $advance->delete());
    }

    /**
     * Advance still to recover per staff member: Σ advances − Σ advance deductions of finalized salary slips.
     *
     * @param  list<int>  $staffIds
     * @return array<int, string> staff id => outstanding
     */
    public function outstanding(array $staffIds): array
    {
        if ($staffIds === []) {
            return [];
        }

        $given = DB::table('staff_advances')->whereIn('staff_member_id', $staffIds)
            ->groupBy('staff_member_id')->pluck(DB::raw('SUM(amount)'), 'staff_member_id');

        $recovered = DB::table('salary_slips as s')->join('salary_runs as r', 'r.id', '=', 's.salary_run_id')
            ->whereIn('s.staff_member_id', $staffIds)->where('r.status', SalaryRunStatus::Finalized->value)
            ->groupBy('s.staff_member_id')
            ->selectRaw('s.staff_member_id AS staff_id, SUM(s.advance_deduction) AS recovered')
            ->pluck('recovered', 'staff_id');

        return collect($staffIds)->mapWithKeys(fn ($id) => [$id => Money::sub((string) ($given[$id] ?? 0), (string) ($recovered[$id] ?? 0))])->all();
    }
}
