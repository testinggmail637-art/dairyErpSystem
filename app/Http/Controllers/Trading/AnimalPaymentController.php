<?php

namespace App\Http\Controllers\Trading;

use App\Enums\AnimalSaleStatus;
use App\Enums\PaymentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trading\AnimalPaymentRequest;
use App\Http\Requests\Trading\DeleteAnimalPaymentRequest;
use App\Models\AnimalPayment;
use App\Models\AnimalPurchase;
use App\Models\AnimalSale;
use App\Models\Party;
use App\Models\PaymentMode;
use App\Services\AnimalPaymentService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Trading → Payments: money paid to / received from parties, in installments (spec Phase 6). */
class AnimalPaymentController extends Controller
{
    public function __construct(private readonly AnimalPaymentService $payments) {}

    public function index(Request $request): View|Response
    {
        $filters = [
            'party_id' => $request->integer('party_id') ?: null,
            'direction' => PaymentDirection::tryFrom((string) $request->query('direction'))?->value ?? '',
            'from' => $this->dateFilter($request->query('from')),
            'to' => $this->dateFilter($request->query('to')),
        ];

        $payments = AnimalPayment::query()
            ->with(['party:id,name,village', 'paymentMode:id,name', 'purchase:id,purchase_no', 'sale:id,sale_no'])
            ->when($filters['party_id'], fn ($q, $id) => $q->where('party_id', $id))
            ->when($filters['direction'], fn ($q, $d) => $q->where('direction', $d))
            ->when($filters['from'], fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $selectedParty = $filters['party_id'] ? Party::withTrashed()->whereKey($filters['party_id'])->pluck('name', 'id')->all() : [];

        return $this->listView($request, 'trading.payments.index', compact('payments', 'filters', 'selectedParty'));
    }

    /** ?party_id=…&direction=…&animal_purchase_id=… / animal_sale_id=… pre-fill the form (from ledger, purchase, sale). */
    public function create(Request $request): View
    {
        $party = Party::query()->find(old('party_id', $request->integer('party_id')) ?: null);

        return view('trading.payments.form', [
            'party' => $party,
            'direction' => old('direction', PaymentDirection::tryFrom((string) $request->query('direction'))?->value ?? PaymentDirection::Paid->value),
            'purchaseId' => old('animal_purchase_id', $request->integer('animal_purchase_id') ?: null),
            'saleId' => old('animal_sale_id', $request->integer('animal_sale_id') ?: null),
            // the party's recent purchases / sales to link the payment to
            'purchases' => $party ? AnimalPurchase::query()->where('party_id', $party->id)->latest('purchase_date')->limit(50)
                ->get(['id', 'purchase_no', 'purchase_date', 'total_amount'])
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->purchase_no} · ".Format::date($p->purchase_date).' · '.Format::money($p->total_amount)])->all() : [],
            'sales' => $party ? AnimalSale::query()->where('party_id', $party->id)->where('status', AnimalSaleStatus::Completed->value)
                ->latest('sale_date')->limit(50)->get(['id', 'sale_no', 'sale_date', 'net_amount'])
                ->mapWithKeys(fn ($s) => [$s->id => "{$s->sale_no} · ".Format::date($s->sale_date).' · '.Format::money($s->net_amount)])->all() : [],
            'modes' => PaymentMode::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(AnimalPaymentRequest $request): RedirectResponse
    {
        [$payment, $created] = $this->payments->record($request->validated());

        return redirect()->route('parties.ledger', $payment->party_id)
            ->with('success', $created ? __('Payment of :amount saved.', ['amount' => Format::money($payment->amount)]) : __('This payment was already recorded.'));
    }

    public function destroy(DeleteAnimalPaymentRequest $request, AnimalPayment $animalPayment): RedirectResponse
    {
        $this->payments->delete($animalPayment);

        return back()->with('success', __('Payment deleted.'));
    }

    private function dateFilter(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
