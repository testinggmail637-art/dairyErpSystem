<?php

namespace App\Exceptions;

use DomainException;

/** Fat / SNF below the chart's minimum and the operator has not chosen yet: reject, or accept at the calculated rate. */
class BelowStandardMilkException extends DomainException
{
    public function __construct(public readonly string $rate, public readonly string $amount)
    {
        parent::__construct(__('Below the minimum fat / SNF. Reject the milk, or accept it at :rate / L (:amount).', [
            'rate' => inr($rate), 'amount' => inr($amount),
        ]));
    }
}
