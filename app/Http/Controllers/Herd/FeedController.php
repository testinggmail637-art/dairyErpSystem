<?php

namespace App\Http\Controllers\Herd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Herd\FeedItemRequest;
use App\Models\FeedItem;
use App\Services\FeedService;
use App\Support\Money;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Herd → Feed (spec Phase 5): the stock screen and feed items. */
class FeedController extends Controller
{
    public function __construct(private readonly FeedService $feed) {}

    /** Stock per item (SQL sums), 90-day average cost, stock value, low-stock flag. */
    public function index(Request $request): View
    {
        $items = FeedItem::query()->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->paginate(100)->withQueryString();
        $ids = $items->pluck('id')->all();
        $stock = $this->feed->stock($ids);
        $cost = $this->feed->averageCost($ids);

        $rows = $items->getCollection()->map(function (FeedItem $item) use ($stock, $cost) {
            $qty = $stock[$item->id] ?? '0.00';
            $avg = $cost[$item->id] ?? null;

            return [
                'item' => $item,
                'stock' => $qty,
                'avg_cost' => $avg,
                'value' => $avg !== null ? Money::round2(Money::mul($qty, $avg)) : null,
                'low' => Money::compare($item->reorder_level, '0') > 0 && Money::compare($qty, $item->reorder_level) <= 0,
            ];
        });

        return view('herd.feed.index', [
            'items' => $items,
            'rows' => $rows,
            'totalValue' => Money::sum($rows->pluck('value')->filter()),
            'lowCount' => $rows->where('low', true)->count(),
            'canManage' => $request->user()->can('feed.manage'),
            'showAll' => $request->boolean('all'),
        ]);
    }

    public function create(): View
    {
        return view('herd.feed.item-form', ['item' => new FeedItem(['is_active' => true, 'reorder_level' => '0'])]);
    }

    public function store(FeedItemRequest $request): RedirectResponse
    {
        $item = FeedItem::create($request->validated());

        return redirect()->route('feed.index')->with('success', __('Feed item :name added. Enter its opening stock to start.', ['name' => $item->name]));
    }

    public function edit(FeedItem $item): View
    {
        return view('herd.feed.item-form', ['item' => $item]);
    }

    public function update(FeedItemRequest $request, FeedItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return redirect()->route('feed.index')->with('success', __('Feed item :name saved.', ['name' => $item->name]));
    }

    public function destroy(FeedItem $item): RedirectResponse
    {
        try {
            $this->feed->deleteItem($item);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('feed.index')->with('success', __('Feed item :name deleted.', ['name' => $item->name]));
    }
}
