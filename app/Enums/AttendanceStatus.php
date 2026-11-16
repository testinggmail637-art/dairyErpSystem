<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A staff member's day (spec Phase 6). Paid days = present + holiday + ½ × half day. */
enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case Leave = 'leave';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('Present'),
            self::Absent => __('Absent'),
            self::HalfDay => __('Half day'),
            self::Leave => __('Leave'),
            self::Holiday => __('Holiday'),
        };
    }

    /** One-letter code for registers and the bulk screen. */
    public function short(): string
    {
        return match ($this) {
            self::Present => 'P',
            self::Absent => 'A',
            self::HalfDay => 'H',
            self::Leave => 'L',
            self::Holiday => 'HO',
        };
    }
}
