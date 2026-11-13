<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A breeding record of a female animal (spec Phase 5). AI / natural service set the expected calving date. */
enum BreedingEventType: string
{
    use HasOptions;

    case Heat = 'heat';
    case Ai = 'ai';
    case NaturalService = 'natural_service';
    case PregnancyCheck = 'pregnancy_check';
    case Abortion = 'abortion';

    public function label(): string
    {
        return match ($this) {
            self::Heat => __('Heat'),
            self::Ai => __('AI'),
            self::NaturalService => __('Natural service'),
            self::PregnancyCheck => __('Pregnancy check'),
            self::Abortion => __('Abortion'),
        };
    }

    public function isService(): bool
    {
        return in_array($this, [self::Ai, self::NaturalService], true);
    }
}
