<?php

namespace App\Http\Controllers\Herd;

use App\Enums\FeedTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\DeleteFeedTransactionRequest;
use App\Http\Requests\Herd\FeedTransactionRequest;
use App\Models\Animal;
use App\Models\ExpenseCategory;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\PaymentMode;
use App\Models\Vendor;
use App\Services\FeedService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Herd → Feed → Entries (spec Phase 5): opening, purchase (+ expense), consumption, adjustments. */
class FeedTransactionController extends Controller
{
    public function __construct(private readonly FeedService $feed) {}

    public function index(Request $request): View|Response
    {
        $valid = fn ($v) => Carbon::canBeCreatedFromFormat((string) $v, 'Y-m-d') ? (string) $v : null;
        $filters = [
            'from' => $valid($request->query('from')) ?? today()->subDays(30)->toDateString(),
            'to' => $valid($request->query('to')) ?? today()->toDateString(),
            'feed_item_id' => (string) ($request->integer('feed_item_id') ?: ''),
            'type' => FeedTransactionType::tryFrom((string) $request->query('type'))?->value ?? '',
        ];

        $entries = FeedTransaction::query()
            ->whereBetween('transaction_date', [$filters['from'], $filters['to']])
            ->when($filters['feed_item_id'], fn ($q, $v) => $q->where('feed_item_id', $v))
            ->when($filters['type'], fn ($q, $v) => $q->where('type', $v))
            ->with(['item:id,name,unit', 'vendor:id,name', 'animal:id,tag_no,name', 'paymentMode:id,name'])
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate(50)->appends(array_filter($filters));

        return $this->listView($request, 'herd.feed.transactions', [
            'entries' => $entries,
            'filters' => $filters,
            'items' => FeedItem::query()->orderBy('name')->pluck('name', 'id'),
            'types' => FeedTransactionType::options(),
            'canManage' => $request->user()->can('feed.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $type = FeedTransactionType::tryFrom((string) $request->query('type')) ?? FeedTransactionType::Purchase;

        return view('herd.feed.transaction-form', $this->formData(new FeedTransaction([
            'type' => $type, 'transaction_date' => today(), 'feed_item_id' => $request->integer('feed_item_id') ?: null,
        ]), null));
    }

    public function store(FeedTransactionRequest $request): RedirectResponse
    {
        try {
            $tx = $this->feed->create($request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('feed.index')->with('success', $tx->expense_id
            ? __(':type saved and :amount added to expenses (Feed).', ['type' => $tx->type->label(), 'amount' => inr($tx->amount)])
            : __(':type saved.', ['type' => $tx->type->label()]));
    }

    public function edit(FeedTransaction $transaction): View
    {
        return view('herd.feed.transaction-form', $this->formData($transaction->load('expense:id,subcategory_id'), $transaction->expense?->subcategory_id));
    }

    public function update(FeedTransactionRequest $request, FeedTransaction $transaction): RedirectResponse
    {
        try {
            $this->feed->update($transaction, $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('feed.transactions.index')->with('success', __(':type saved.', ['type' => $transaction->type->label()]));
    }

    public function destroy(DeleteFeedTransactionRequest $request, FeedTransaction $transaction): RedirectResponse
    {
        try {
            $this->feed->delete($transaction);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Feed entry deleted (with its expense, if any).'));
    }

    /** @return array<string, mixed> */
    private function formData(FeedTransaction $tx, ?int $subcategoryId): array
    {
        $vendor = $tx->vendor_id ? Vendor::withTrashed()->find($tx->vendor_id, ['id', 'name']) : null;
        $animal = $tx->animal_id ? Animal::withTrashed()->find($tx->animal_id, ['id', 'tag_no', 'name']) : null;
        $feed = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Feed')->first();

        return [
            'tx' => $tx,
            'items' => FeedItem::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $tx->feed_item_id))
                ->orderBy('name')->get(['id', 'name', 'unit'])->mapWithKeys(fn ($i) => [$i->id => $i->name.' ('.$i->unit->label().')']),
            'types' => FeedTransactionType::options(),
            'vendorOptions' => $vendor ? [$vendor->id => $vendor->name] : [],
            'animalOptions' => $animal ? [$animal->id => $animal->label()] : [],
            'paymentModes' => PaymentMode::query()->where('is_active', true)->where('code', '!=', 'CARD')->orderBy('name')->pluck('name', 'id'),
            'subcategories' => $feed ? $feed->children()->orderBy('name')->pluck('name', 'id') : [],
            'subcategoryId' => $subcategoryId,
        ];
    }
}
