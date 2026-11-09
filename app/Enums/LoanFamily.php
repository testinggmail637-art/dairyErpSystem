<?php

namespace App\Enums;

/** The three ways money can be borrowed (spec §6 Phase 3A): decides the screens and the maths. */
enum LoanFamily: string
{
    case Instalment = 'instalment';
    case Revolving = 'revolving';
    case CreditCard = 'credit_card';

    public function label(): string
    {
        return match ($this) {
            self::Instalment => __('Instalment loans'),
            self::Revolving => __('Overdraft / cash credit / KCC'),
            self::CreditCard => __('Credit cards'),
        };
    }
}
