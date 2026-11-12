<?php

namespace App\Http\Controllers\Collection;

use App\Enums\FarmerPaymentStatus;
use App\Exports\FarmerBankSheetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\FarmerPaymentReasonRequest;
use App\Http\Requests\Collection\FarmerStatementRequest;
use App\Http\Requests\Collection\MarkFarmerPaidRequest;
use App\Http\Requests\Collection\PaymentCycleRequest;
use App\Models\FarmerPayment;
use App\Models\FarmerPaymentCycle;
use App\Models\PaymentMode;
use App\Services\FarmerPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Collection → Farmer Payments (spec Phase 4): cycles, draft statements, finalize, mark paid. */
class FarmerPaymentController extends Controller
{
    public function __construct(private readonly FarmerPaymentService $payments) {}

    public function index(Request $request): View|Response
    {
        $paid = FarmerPaymentStatus::Paid->value;
        $cycles = FarmerPaymentCycle::query()
            ->withCount('payments')
            ->withSum('payments as gross', 'gross_amount')
            ->withSum('payments as net', 'net_payable')
            ->withSum(['payments as paid' => fn ($q) => $q->where('status', $paid)], 'net_payable')
            ->orderByDesc('from_date')
            ->paginate(25);

        [$from, $to] = $this->payments->suggestedRange();

        return $this->listView($request, 'collection.payments.index', ['cycles' => $cycles, 'from' => $from, 'to' => $to]);
    }

    public function store(PaymentCycleRequest $request): RedirectResponse
    {
        try {
            $cycle = $this->payments->createCycle($request->validated('from_date'), $request->validated('to_date'));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('farmer-payments.show', $cycle)
            ->with('success', __('Cycle :no made with :n draft statements. Check bonus and deductions, then finalize.', ['no' => $cycle->cycle_no, 'n' => $cycle->payments()->count()]));
    }

    public function show(FarmerPaymentCycle $farmerPayment): View
    {
        $cycle = $farmerPayment;
        $statements = $cycle->payments()->with(['farmer:id,code,name,bank_account_no,upi_id', 'paymentMode:id,name'])
            ->join('farmers', 'farmers.id', '=', 'farmer_payments.farmer_id')->orderBy('farmers.code')
            ->select('farmer_payments.*')->get();

        return view('collection.payments.show', [
            'cycle' => $cycle->load('finalizer:id,name'),
            'statements' => $statements,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'canManage' => request()->user()->can('farmer_payments.manage'),
        ]);
    }

    public function destroy(FarmerPaymentReasonRequest $request, FarmerPaymentCycle $farmerPayment): RedirectResponse
    {
        try {
            $this->payments->deleteCycle($farmerPayment);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('farmer-payments.index')->with('success', __('Draft cycle :no deleted. Its milk can be changed again.', ['no' => $farmerPayment->cycle_no]));
    }

    public function finalize(Request $request, FarmerPaymentCycle $farmerPayment): RedirectResponse
    {
        try {
            $this->payments->finalize($farmerPayment);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Cycle :no finalized. Now pay the farmers.', ['no' => $farmerPayment->cycle_no]));
    }

    public function payAll(MarkFarmerPaidRequest $request, FarmerPaymentCycle $farmerPayment): RedirectResponse
    {
        try {
            $count = $this->payments->markAllPaidCash($farmerPayment, $request->validated('paid_on'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':n statements marked paid in cash.', ['n' => $count]));
    }

    public function statementPdf(FarmerPayment $statement): Response
    {
        $name = str_replace('/', '-', $statement->cycle->cycle_no).'-'.$statement->farmer->code.'.pdf';

        return $this->payments->renderStatementPdf($statement)->stream($name);
    }

    public function bankSheet(FarmerPaymentCycle $farmerPayment): BinaryFileResponse|RedirectResponse
    {
        try {
            $sheet = $this->payments->bankSheet($farmerPayment);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return Excel::download(new FarmerBankSheetExport($sheet), str_replace('/', '-', $farmerPayment->cycle_no).'-bank-sheet.xlsx');
    }

    public function editStatement(FarmerPayment $statement): View
    {
        return view('collection.payments.statement', [
            'statement' => $statement->load(['farmer:id,code,name', 'cycle']),
            'outstandingAdvance' => $this->payments->outstandingAdvances([$statement->farmer_id])[$statement->farmer_id],
        ]);
    }

    public function updateStatement(FarmerStatementRequest $request, FarmerPayment $statement): RedirectResponse
    {
        try {
            $this->payments->updateStatement($statement, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('farmer-payments.show', $statement->farmer_payment_cycle_id)
            ->with('success', __('Statement of :code saved: net :net.', ['code' => $statement->farmer->code, 'net' => inr($statement->net_payable)]));
    }

    public function pay(MarkFarmerPaidRequest $request, FarmerPayment $statement): RedirectResponse
    {
        try {
            $this->payments->markPaid($statement, $request->validated());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':code paid :net.', ['code' => $statement->farmer->code, 'net' => inr($statement->net_payable)]));
    }

    public function unpay(FarmerPaymentReasonRequest $request, FarmerPayment $statement): RedirectResponse
    {
        $this->payments->markUnpaid($statement);

        return back()->with('success', __('Payment of :code undone.', ['code' => $statement->farmer->code]));
    }
}
