<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToFarm;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A staff member's attendance on one day (spec Phase 6). */
class Attendance extends Model
{
    use BelongsToFarm, TracksUser;

    /** @var list<string> */
    protected $fillable = ['farm_id', 'staff_member_id', 'attendance_date', 'status', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }
}
