<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CustomerPaymentRequest;
use App\Http\Requests\Sales\DeleteCustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMode;
use App\Services\CustomerPaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Sales → Payments (spec Section 6): money from customers, set against bills oldest first. Users without
 * customer_payments.edit (delivery person) see only the payments they collected.
 */
class CustomerPaymentController extends Controller
{
    public function __construct(private readonly CustomerPaymentService $payments) {}

    public function index(Request $request): View|Response
    {
        $valid = fn (string $key) => Carbon::canBeCreatedFromFormat((string) $request->query($key), 'Y-m-d') ? (string) $request->query($key) : '';
        $filters = [
            'search' => trim((string) $request->query('search')),
            'from' => $valid('from'),
            'to' => $valid('to'),
            'payment_mode_id' => ctype_digit((string) $request->query('payment_mode_id')) ? (string) $request->query('payment_mode_id') : '',
        ];
        $user = $request->user();

        $base = CustomerPayment::query()
            ->unless($user->can('customer_payments.edit'), fn ($q) => $q->where('collected_by', $user->id))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference_no', $filters['search'])
                ->orWhereHas('customer', fn ($c) => $c->withTrashed()->search($filters['search']))))
            ->when($filters['from'], fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->when($filters['payment_mode_id'], fn ($q, $id) => $q->where('payment_mode_id', $id));

        $payments = (clone $base)
            ->with(['customer:id,code,name', 'paymentMode:id,name', 'collector:id,name'])
            ->withSum('allocations as allocated', 'amount')
            ->latest('payment_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'sales.payments.index', [
            'payments' => $payments,
            'total' => (clone $base)->sum('amount'),
            'filters' => $filters,
            'modes' => PaymentMode::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('sales.payments.form', $this->formData(new CustomerPayment([
            'customer_id' => $request->integer('customer_id') ?: null,
            'payment_date' => now()->toDateString(),
        ])));
    }

    public function store(CustomerPaymentRequest $request): RedirectResponse
    {
        [$payment, $created] = $this->payments->record($request->validated());

        return redirect()->route('customer-payments.index')->with('success', $created
            ? __('Payment of :amount from :name recorded.', ['amount' => inr($payment->amount), 'name' => $payment->customer->name])
            : __('This payment was already recorded.'));
    }

    public function edit(CustomerPayment $customerPayment): View
    {
        return view('sales.payments.form', $this->formData($customerPayment->load('allocations.bill:id,bill_no,bill_date')));
    }

    public function update(CustomerPaymentRequest $request, CustomerPayment $customerPayment): RedirectResponse
    {
        $this->payments->update($customerPayment, $request->validated());

        return redirect()->route('customer-payments.index')->with('success', __('Payment updated.'));
    }

    public function destroy(DeleteCustomerPaymentRequest $request, CustomerPayment $customerPayment): RedirectResponse
    {
        $this->payments->delete($customerPayment);

        return redirect()->route('customer-payments.index')->with('success', __('Payment deleted.'));
    }

    /** @return array<string, mixed> */
    private function formData(CustomerPayment $payment): array
    {
        $customerId = old('customer_id', $payment->customer_id);
        $customer = $customerId ? Customer::withTrashed()->find($customerId, ['id', 'code', 'name']) : null;
        $current = $payment->payment_mode_id;

        return [
            'payment' => $payment,
            'customer' => $customer,
            'selectedCustomer' => $customer ? [$customer->id => "{$customer->code} · {$customer->name}"] : [],
            'paymentModes' => PaymentMode::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($current, fn ($q) => $q->orWhere('id', $current)))
                ->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
