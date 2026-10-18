<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Support\CurrentFarm;
use App\Support\Money;
use App\Support\ReceiptStorage;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expenses (spec 7.1). Saved directly — no approval. Credit expenses carry a vendor and no payment mode;
 * paid expenses carry a payment mode. Receipts live on the private disk.
 */
class ExpenseService
{
    public function __construct(private readonly CurrentFarm $currentFarm) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, ?UploadedFile $receipt = null): Expense
    {
        $path = $receipt ? ReceiptStorage::store($receipt, $this->currentFarm->id()) : null;

        try {
            return DB::transaction(fn () => Expense::create($this->attributes($data) + ['receipt_path' => $path]));
        } catch (\Throwable $e) {
            ReceiptStorage::delete($path);

            throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    public function update(Expense $expense, array $data, ?UploadedFile $receipt = null, bool $removeReceipt = false): Expense
    {
        $this->ensureEditable($expense);

        $oldPath = $expense->receipt_path;
        $newPath = $receipt ? ReceiptStorage::store($receipt, $expense->farm_id) : null;
        $attributes = $this->attributes($data);

        if ($newPath || $removeReceipt) {
            $attributes['receipt_path'] = $newPath;
        }

        try {
            DB::transaction(fn () => $expense->update($attributes));
        } catch (\Throwable $e) {
            ReceiptStorage::delete($newPath);

            throw $e;
        }

        if (($newPath || $removeReceipt) && $oldPath) {
            ReceiptStorage::delete($oldPath);
        }

        return $expense;
    }

    /** Soft delete; the receipt file is kept with the (deleted) record for the audit trail. */
    public function delete(Expense $expense): void
    {
        $this->ensureEditable($expense);

        $expense->delete();
    }

    /** Auto-created expenses are managed from their source (treatment, feed purchase, salary run). */
    public function ensureEditable(Expense $expense): void
    {
        if ($expense->isAutoCreated()) {
            throw new DomainException(__('This expense was created automatically. Change it from where it came from.'));
        }
    }

    /**
     * A create-only user's own entries they may still change: today's (IST), or all of them when the farm
     * setting staff_same_day_edit_only is off (spec 4.5).
     *
     * @return Builder<Expense>
     */
    public function ownEditableQuery(User $user): Builder
    {
        $sameDayOnly = (bool) ($this->currentFarm->farm()?->setting('staff_same_day_edit_only') ?? true);

        return Expense::query()
            ->where('created_by', $user->getKey())
            ->when($sameDayOnly, fn ($q) => $q->where('created_at', '>=', now()->startOfDay()));
    }

    /**
     * Expense list / export / totals filters (all optional), each backed by a (farm_id, …, expense_date) index.
     *
     * @param  Builder<Expense>  $query
     * @param  array<string, mixed>  $f
     * @return Builder<Expense>
     */
    public function applyFilters(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->when($f['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($f['subcategory_id'] ?? null, fn ($q, $id) => $q->where('subcategory_id', $id))
            ->when($f['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
            ->when($f['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($f['payment_mode_id'] ?? null, fn ($q, $id) => $q->where('payment_mode_id', $id))
            ->when(($f['type'] ?? null) === 'paid', fn ($q) => $q->where('is_credit', false))
            ->when(($f['type'] ?? null) === 'credit', fn ($q) => $q->where('is_credit', true))
            ->when($f['created_by'] ?? null, fn ($q, $id) => $q->where('created_by', $id))
            ->when($f['import_batch_id'] ?? null, fn ($q, $id) => $q->where('import_batch_id', $id))
            ->when(filled($f['search'] ?? null), fn ($q) => $q->where(fn ($q) => $q
                ->where('paid_to_text', 'like', '%'.$f['search'].'%')
                ->orWhere('note', 'like', '%'.$f['search'].'%')));
    }

    /**
     * Totals of the filtered set in one SQL query (never summed in PHP).
     *
     * @param  Builder<Expense>  $query
     * @return array{count: int, total: string, paid: string, credit: string}
     */
    public function totals(Builder $query): array
    {
        $row = (clone $query)->toBase()
            ->reorder()
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_credit = 0 THEN amount END), 0) AS paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_credit = 1 THEN amount END), 0) AS credit')
            ->first();

        return [
            'count' => (int) $row->cnt,
            'total' => Money::round2((string) $row->total),
            'paid' => Money::round2((string) $row->paid),
            'credit' => Money::round2((string) $row->credit),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $isCredit = (bool) ($data['is_credit'] ?? false);
        $hasQuantity = filled($data['quantity'] ?? null);

        return [
            'expense_date' => Carbon::parse($data['expense_date'])->toDateString(),
            'amount' => Money::round2($data['amount']),
            'category_id' => $data['category_id'],
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'section_id' => $data['section_id'],
            'vendor_id' => $data['vendor_id'] ?? null,
            'paid_to_text' => filled($data['vendor_id'] ?? null) ? null : ($data['paid_to_text'] ?? null),
            'is_credit' => $isCredit,
            'payment_mode_id' => $isCredit ? null : ($data['payment_mode_id'] ?? null),
            'quantity' => $hasQuantity ? Money::round2($data['quantity']) : null,
            'unit' => $hasQuantity ? ($data['unit'] ?? null) : null,
            'note' => $data['note'] ?? null,
        ];
    }
}
