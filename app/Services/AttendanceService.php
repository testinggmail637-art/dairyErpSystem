<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\StaffMember;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Daily attendance (spec Phase 6): one row per staff member and day, saved for the whole staff at once. */
class AttendanceService
{
    /**
     * Staff to mark on a date: employed that day (joined on / before it, not left before it) and either still active or
     * left later (so past days of a worker who left can still be corrected), plus anyone who already has a row that day.
     *
     * @return Collection<int, StaffMember>
     */
    public function staffOn(string $date): Collection
    {
        $marked = Attendance::query()->whereDate('attendance_date', $date)->select('staff_member_id');

        return StaffMember::query()
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('joining_date', '<=', $date)
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->whereNull('leaving_date'))
                    ->orWhere('leaving_date', '>=', $date)))
                ->orWhereIn('id', $marked))
            ->orderBy('name')
            ->get(['id', 'name', 'designation']);
    }

    /** @return \Illuminate\Support\Collection<int, Attendance> staff id => that day's row */
    public function rowsOn(string $date): \Illuminate\Support\Collection
    {
        return Attendance::query()->whereDate('attendance_date', $date)->get()->keyBy('staff_member_id');
    }

    /**
     * Saves the day: a status creates / updates the row, an empty status removes it (not marked).
     *
     * @param  array<int|string, array{status: ?string, note: ?string}>  $rows  staff id => fields
     * @return int rows saved
     */
    public function saveDay(string $date, array $rows): int
    {
        if (app(SalaryRunService::class)->isMonthLocked($date)) {
            throw new DomainException(__('Salary for this month is finalized, so its attendance can no longer change.'));
        }

        $allowed = $this->staffOn($date)->modelKeys();

        return DB::transaction(function () use ($date, $rows, $allowed) {
            $existing = $this->rowsOn($date);
            $saved = 0;

            foreach ($rows as $staffId => $row) {
                if (! in_array((int) $staffId, $allowed, true)) {
                    continue;
                }

                $current = $existing->get((int) $staffId);

                if (($row['status'] ?? null) === null) {
                    $current?->delete();

                    continue;
                }

                $fields = ['status' => $row['status'], 'note' => $row['note'] ?? null];
                $current ? $current->update($fields) : Attendance::create($fields + ['staff_member_id' => (int) $staffId, 'attendance_date' => $date]);
                $saved++;
            }

            return $saved;
        });
    }
}
