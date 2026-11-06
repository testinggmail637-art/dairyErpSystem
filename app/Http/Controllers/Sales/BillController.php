<?php

namespace App\Http\Controllers\Sales;

use App\Enums\BillStatus;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CancelBillRequest;
use App\Http\Requests\Sales\GenerateBillsRequest;
use App\Jobs\GenerateBills;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Delivery;
use App\Services\BillingService;
use App\Support\CurrentFarm;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales → Bills (spec Section 6 billing rules). */
class BillController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => (string) $request->query('status'),
            'month' => preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : '',
        ];

        $bills = Bill::query()
            ->with('customer:id,code,name')->withPaid()
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('bill_no', $filters['search'])
                ->orWhereHas('customer', fn ($c) => $c->withTrashed()->search($filters['search']))))
            ->when(BillStatus::tryFrom($filters['status']), fn ($q, $s) => $q->where('status', $s))
            ->when($filters['month'] !== '', fn ($q) => $q->whereBetween('bill_date', [
                $filters['month'].'-01', date('Y-m-t', strtotime($filters['month'].'-01')),
            ]))
            ->latest('bill_date')->latest('id')
            ->paginate(25)
            ->withQueryString();

        return $this->listView($request, 'sales.bills.index', compact('bills', 'filters'));
    }

    public function create(Request $request): View
    {
        $from = now()->subMonthNoOverflow()->startOfMonth();
        $customerId = old('customer_id', $request->integer('customer_id') ?: null);
        $customer = $customerId ? Customer::query()->find($customerId, ['id', 'code', 'name']) : null;

        return view('sales.bills.create', [
            'from' => $from->toDateString(),
            'to' => $from->copy()->endOfMonth()->toDateString(),
            'selectedCustomer' => $customer ? [$customer->id => "{$customer->code} · {$customer->name}"] : [],
        ]);
    }

    public function store(GenerateBillsRequest $request, CurrentFarm $farm): RedirectResponse
    {
        $data = $request->validated();

        if ($data['scope'] === 'all') {
            GenerateBills::dispatch($farm->farm(), $request->user(), $data['period_from'], $data['period_to'], $data['bill_date']);

            return redirect()->route('bills.index', ['status' => 'draft'])
                ->with('success', __('Making draft bills for every customer with unbilled milk. You will get a bell notification when it is done.'));
        }

        try {
            $bill = $this->billing->generate(Customer::query()->findOrFail($data['customer_id']), $data['period_from'], $data['period_to'], $data['bill_date']);
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $bill)->with('success', __('Draft bill made. Check it, then finalize.'));
    }

    public function show(Bill $bill): View
    {
        $bill->load(['customer', 'items.product:id,name', 'finalizedBy:id,name', 'cancelledBy:id,name', 'allocations.payment:id,payment_date,payment_mode_id', 'allocations.payment.paymentMode:id,name']);

        return view('sales.bills.show', [
            'bill' => $bill,
            'rows' => $bill->dayRows(),
            'balances' => $bill->isDraft() ? $this->billing->balancePreview($bill) : null,
            'pendingInPeriod' => $bill->isDraft() ? Delivery::query()->where('customer_id', $bill->customer_id)
                ->where('status', DeliveryStatus::Pending)
                ->whereBetween('delivery_date', [$bill->period_from->toDateString(), $bill->period_to->toDateString()])->count() : 0,
        ]);
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        return $this->attempt(fn () => $this->billing->deleteDraft($bill), route('bills.index'), __('Draft deleted; its deliveries can be billed again.'));
    }

    public function finalize(Bill $bill): RedirectResponse
    {
        return $this->attempt(fn () => $this->billing->finalize($bill), route('bills.show', $bill), __('Bill finalized.'));
    }

    public function cancel(CancelBillRequest $request, Bill $bill): RedirectResponse
    {
        return $this->attempt(fn () => $this->billing->cancel($bill, $request->validated('reason')), route('bills.show', $bill),
            __('Bill cancelled; its deliveries can be billed again.'));
    }

    public function pdf(Bill $bill): Response|StreamedResponse
    {
        if ($bill->isDraft()) {
            return $this->billing->renderPdf($bill)->stream(str_replace('/', '-', $bill->label()).'.pdf');
        }

        return Storage::disk(BillingService::PDF_DISK)->response($this->billing->pdfPath($bill), basename($bill->pdf_path ?? 'bill.pdf'));
    }

    private function attempt(callable $action, string $redirect, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to($redirect)->with('success', $message);
    }
}
