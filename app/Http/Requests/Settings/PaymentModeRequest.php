<?php

namespace App\Http\Requests\Settings;

class PaymentModeRequest extends MasterDataRequest
{
    protected function table(): string
    {
        return 'payment_modes';
    }

    protected function routeParameter(): string
    {
        return 'payment_mode';
    }
}
