<?php

namespace App\Services;

use App\Enums\FeedTransactionType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeedItem;
use App\Models\FeedTransaction;
use App\Models\Section;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Feed stock (spec Phase 5). Stock = Σ(opening, purchase, adjustment in) − Σ(consumption, adjustment out), summed in SQL.
 * A purchase also keeps exactly one expense (Feed, section HERD, vendor, paid or credit, quantity + unit,
 * source = the purchase) in the same transaction; consumption only lowers stock — stock value is never a second
 * expense. No change may leave an item's stock below zero (the item row is locked while checking).
 * Average cost per unit = Σ purchase amount ÷ Σ purchase quantity of the last 90 days.
 */
class FeedService
{
    /**
     * @param  list<int>|null  $itemIds  null = all items
     * @return array<int, string> item id => stock
     */
    public function stock(?array $itemIds = null): array
    {
        return FeedTransaction::query()->toBase()
            ->when($itemIds !== null, fn ($q) => $q->whereIn('feed_item_id', $itemIds))
            ->groupBy('feed_item_id')
            ->selectRaw('feed_item_id, SUM(CASE WHEN type IN (?, ?, ?) THEN quantity ELSE -quantity END) AS stock', FeedTransactionType::inValues())
            ->pluck('stock', 'feed_item_id')
            ->map(fn ($s) => Money::round2((string) $s))
            ->all();
    }

    /**
     * @param  list<int>|null  $itemIds
     * @return array<int, string> item id => average purchase cost per unit, last 90 days (items without purchases left out)
     */
    public function averageCost(?array $itemIds = null): array
    {
        return FeedTransaction::query()->toBase()
            ->where('type', FeedTransactionType::Purchase->value)
            ->where('transaction_date', '>=', today()->subDays(90)->toDateString())
            ->when($itemIds !== null, fn ($q) => $q->whereIn('feed_item_id', $itemIds))
            ->groupBy('feed_item_id')
            ->selectRaw('feed_item_id, SUM(amount) AS amount, SUM(quantity) AS qty')
            ->get()
            ->filter(fn ($r) => Money::compare((string) $r->qty, '0') > 0)
            ->mapWithKeys(fn ($r) => [$r->feed_item_id => Money::round2(Money::div((string) $r->amount, (string) $r->qty))])
            ->all();
    }

    /** @param  array<string, mixed>  $data  validated FeedTransactionRequest */
    public function create(array $data): FeedTransaction
    {
        return DB::transaction(function () use ($data) {
            $item = FeedItem::query()->lockForUpdate()->findOrFail($data['feed_item_id']);
            $tx = FeedTransaction::create($this->fields($data));
            $this->ensureStock($item);
            $this->syncExpense($tx, $data['expense_subcategory_id'] ?? null);

            return $tx;
        });
    }

    /** @param  array<string, mixed>  $data  validated FeedTransactionRequest */
    public function update(FeedTransaction $tx, array $data): FeedTransaction
    {
        return DB::transaction(function () use ($tx, $data) {
            $items = FeedItem::query()->whereKey(array_unique([$tx->feed_item_id, (int) $data['feed_item_id']]))->lockForUpdate()->get();
            $tx->update($this->fields($data));
            $items->each(fn (FeedItem $item) => $this->ensureStock($item));
            $this->syncExpense($tx, $data['expense_subcategory_id'] ?? $tx->expense?->subcategory_id);

            return $tx;
        });
    }

    public function delete(FeedTransaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $item = FeedItem::query()->lockForUpdate()->findOrFail($tx->feed_item_id);
            $expense = $tx->expense;
            $tx->delete();
            $this->ensureStock($item);
            $expense?->delete();
        });
    }

    /** An item that was ever used is made inactive instead. */
    public function deleteItem(FeedItem $item): void
    {
        if ($item->transactions()->exists()) {
            throw new DomainException(__(':name has stock entries — mark it inactive instead.', ['name' => $item->name]));
        }
        $item->delete();
    }

    private function ensureStock(FeedItem $item): void
    {
        $stock = $this->stock([$item->id])[$item->id] ?? '0.00';

        if (Money::isNegative($stock)) {
            throw new DomainException(__('Not enough :name in stock — this would leave :stock :unit.', [
                'name' => $item->name, 'stock' => fmt_qty($stock), 'unit' => $item->unit->label(),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $type = FeedTransactionType::from($data['type']);
        $qty = Money::round2((string) $data['quantity']);
        $isPurchase = $type === FeedTransactionType::Purchase;
        $amount = filled($data['amount'] ?? null) ? Money::round2((string) $data['amount']) : null;
        $rate = filled($data['rate'] ?? null) ? Money::round2((string) $data['rate']) : null;

        // a purchase is entered by its bill total; the rate follows from it (opening may give a rate for its value)
        if ($amount !== null) {
            $rate = Money::round2(Money::div($amount, $qty));
        } elseif ($rate !== null && $type->isIn()) {
            $amount = Money::round2(Money::mul($qty, $rate));
        }

        return [
            'feed_item_id' => $data['feed_item_id'],
            'transaction_date' => $data['transaction_date'],
            'type' => $type,
            'quantity' => $qty,
            'rate' => $type->isIn() ? $rate : null,
            'amount' => $type->isIn() ? $amount : null,
            'vendor_id' => $isPurchase ? ($data['vendor_id'] ?? null) : null,
            'is_credit' => $isPurchase && ($data['is_credit'] ?? false),
            'payment_mode_id' => $isPurchase && ! ($data['is_credit'] ?? false) ? ($data['payment_mode_id'] ?? null) : null,
            'animal_id' => $type === FeedTransactionType::Consumption ? ($data['animal_id'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Create / update the purchase's expense, or remove it when the row is no longer a purchase. */
    private function syncExpense(FeedTransaction $tx, int|string|null $subcategoryId): void
    {
        $expense = $tx->expense;

        if ($tx->type !== FeedTransactionType::Purchase) {
            if ($expense) {
                $tx->update(['expense_id' => null]);
                $expense->delete();
            }

            return;
        }

        $feed = ExpenseCategory::query()->whereNull('parent_id')->where('name', 'Feed')->firstOrFail();
        $item = $tx->item()->first(['id', 'name', 'unit']);
        $expense ??= (new Expense)->forceFill(['source_type' => $tx->getMorphClass(), 'source_id' => $tx->getKey()]);

        $expense->fill([
            'expense_date' => $tx->transaction_date->toDateString(),
            'amount' => $tx->amount,
            'category_id' => $feed->id,
            'subcategory_id' => $subcategoryId ? $feed->children()->whereKey($subcategoryId)->value('id') : null,
            'section_id' => Section::query()->where('code', 'HERD')->value('id'),
            'vendor_id' => $tx->vendor_id,
            'is_credit' => $tx->is_credit,
            'payment_mode_id' => $tx->payment_mode_id,
            'quantity' => $tx->quantity,
            'unit' => $item->unit->value,
            'note' => __('Feed purchase: :qty :unit :name', ['qty' => fmt_qty($tx->quantity), 'unit' => $item->unit->label(), 'name' => $item->name]),
        ])->save();

        if ($tx->expense_id !== $expense->id) {
            $tx->update(['expense_id' => $expense->id]);
        }
    }
}
