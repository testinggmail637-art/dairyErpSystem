<?php

namespace App\Http\Controllers\Settings;

use App\Http\Requests\Settings\PaymentModeRequest;
use App\Models\PaymentMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Settings → Payment Modes. */
class PaymentModeController extends MasterDataController
{
    protected function model(): string
    {
        return PaymentMode::class;
    }

    protected function routePrefix(): string
    {
        return 'payment-modes';
    }

    protected function labels(): array
    {
        return ['plural' => __('Payment modes'), 'singular' => __('Payment mode'), 'icon' => 'bi-wallet2'];
    }

    public function store(PaymentModeRequest $request): RedirectResponse
    {
        return $this->saveNew($request);
    }

    public function edit(PaymentMode $paymentMode): View
    {
        return $this->editRow($paymentMode);
    }

    public function update(PaymentModeRequest $request, PaymentMode $paymentMode): RedirectResponse
    {
        return $this->saveExisting($request, $paymentMode);
    }
}
