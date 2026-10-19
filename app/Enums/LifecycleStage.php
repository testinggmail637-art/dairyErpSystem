<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Life stage of an animal (spec Section 6, Phase 2). Only milking, active females appear in milk entry. */
enum LifecycleStage: string
{
    use HasOptions;

    case Calf = 'calf';
    case Heifer = 'heifer';
    case PregnantHeifer = 'pregnant_heifer';
    case Milking = 'milking';
    case Dry = 'dry';
    case Bull = 'bull';

    public function label(): string
    {
        return match ($this) {
            self::Calf => __('Calf'),
            self::Heifer => __('Heifer'),
            self::PregnantHeifer => __('Pregnant heifer'),
            self::Milking => __('Milking'),
            self::Dry => __('Dry'),
            self::Bull => __('Bull'),
        };
    }

    /**
     * Stages allowed for a gender (spec: males are only calf or bull; a bull is male).
     *
     * @return list<self>
     */
    public static function forGender(AnimalGender $gender): array
    {
        return $gender === AnimalGender::Male
            ? [self::Calf, self::Bull]
            : [self::Calf, self::Heifer, self::PregnantHeifer, self::Milking, self::Dry];
    }
}
