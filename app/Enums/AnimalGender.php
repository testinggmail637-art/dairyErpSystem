<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Gender of an animal (spec Section 6, Phase 2). Males can only be calf or bull. */
enum AnimalGender: string
{
    use HasOptions;

    case Female = 'female';
    case Male = 'male';

    public function label(): string
    {
        return match ($this) {
            self::Female => __('Female'),
            self::Male => __('Male'),
        };
    }
}
