<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\DeleteVendorPaymentRequest;
use App\Http\Requests\Expenses\VendorPaymentRequest;
use App\Models\PaymentMode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\VendorPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Record / edit / delete payments to vendors ("Record payment" on the vendor ledger).
 */
class VendorPaymentController extends Controller
{
    public function __construct(private readonly VendorPaymentService $payments) {}

    public function create(Request $request): View
    {
        $vendor = Vendor::query()->withBalance()->findOrFail($request->integer('vendor_id'));

        return view('expenses.vendor-payments.form', $this->formData(new VendorPayment([
            'vendor_id' => $vendor->id,
            'payment_date' => now()->toDateString(),
        ]), $vendor));
    }

    public function store(VendorPaymentRequest $request): RedirectResponse
    {
        [$payment, $created] = $this->payments->create($request->validated());

        return $this->backToLedger($payment, $created
            ? __('Payment of :amount to :vendor recorded.', ['amount' => inr($payment->amount), 'vendor' => $payment->vendor->name])
            : __('This payment was already recorded.'));
    }

    public function edit(VendorPayment $vendorPayment): View
    {
        $vendor = Vendor::query()->withTrashed()->withBalance()->findOrFail($vendorPayment->vendor_id);

        return view('expenses.vendor-payments.form', $this->formData($vendorPayment, $vendor));
    }

    public function update(VendorPaymentRequest $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $this->payments->update($vendorPayment, $request->validated());

        return $this->backToLedger($vendorPayment, __('Payment updated.'));
    }

    public function destroy(DeleteVendorPaymentRequest $request, VendorPayment $vendorPayment): RedirectResponse
    {
        $this->payments->delete($vendorPayment);

        return $this->backToLedger($vendorPayment, __('Payment deleted.'));
    }

    private function backToLedger(VendorPayment $payment, string $message): RedirectResponse
    {
        return request()->user()->can('vendors.view')
            ? redirect()->route('vendors.show', $payment->vendor_id)->with('success', $message)
            : redirect()->route('home')->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function formData(VendorPayment $payment, Vendor $vendor): array
    {
        $current = $payment->payment_mode_id;

        return [
            'payment' => $payment,
            'vendor' => $vendor,
            'paymentModes' => PaymentMode::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)))
                ->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
