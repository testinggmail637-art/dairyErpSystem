<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\ExpenseUnit;
use App\Exports\ExpensesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseIndexRequest;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ExpenseService;
use App\Support\CurrentFarm;
use App\Support\Exporter;
use App\Support\ReceiptStorage;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses (spec 7.1): list with filters + totals, create/edit with receipt, soft delete, Excel export.
 * Saved expenses count in lists and reports at once — there is no approval step.
 */
class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly CurrentFarm $currentFarm,
    ) {}

    public function index(ExpenseIndexRequest $request): View|Response
    {
        $filters = $request->filters();
        $query = $this->expenses->applyFilters(Expense::query(), $filters);
        $totals = $this->expenses->totals($query);

        $expenses = (clone $query)
            ->with(['category:id,name', 'subcategory:id,name', 'section:id,name', 'vendor:id,name', 'paymentMode:id,name', 'creator:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(50, ['*'], 'page', null, $totals['count']) // reuse the totals count: no second COUNT(*)
            ->appends(array_filter($filters, fn ($v) => $v !== null && $v !== '')); // effective filters incl. the default month

        return $this->listView($request, 'expenses.expenses.index', [
            'expenses' => $expenses,
            'totals' => $totals,
            'filters' => $filters,
        ] + $this->filterOptions($filters));
    }

    public function create(Request $request): View
    {
        return view('expenses.expenses.form', $this->formData(new Expense([
            'expense_date' => now()->toDateString(),
            'is_credit' => false,
            'section_id' => $request->integer('section_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
        ])));
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = $this->expenses->create($request->validated(), $request->file('receipt'));
        $message = __('Expense of :amount saved.', ['amount' => inr($expense->amount)]);

        // "Save & add another" (and users who may add but not see the list, e.g. herd staff): back to an empty form
        // that keeps section and category for quick entry.
        if ($request->boolean('add_another') || ! $request->user()->can('expenses.view')) {
            return redirect()->route('expenses.create', [
                'section_id' => $expense->section_id,
                'category_id' => $expense->category_id,
            ])->with('success', $message);
        }

        return redirect()->route('expenses.index')->with('success', $message);
    }

    public function show(Expense $expense): View
    {
        return view('expenses.expenses.show', [
            'expense' => $expense->load(['category', 'subcategory', 'section', 'vendor', 'paymentMode', 'creator', 'updater']),
        ]);
    }

    public function edit(Expense $expense): View|RedirectResponse
    {
        if ($expense->isAutoCreated()) {
            return redirect()->route('expenses.show', $expense)
                ->with('warning', __('This expense was created automatically. Change it from where it came from.'));
        }

        return view('expenses.expenses.form', $this->formData($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $this->expenses->update($expense, $request->validated(), $request->file('receipt'), $request->boolean('remove_receipt'));
        } catch (DomainException $e) {
            return redirect()->route('expenses.show', $expense)->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index')->with('success', __('Expense updated.'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        try {
            $this->expenses->delete($expense);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index')->with('success', __('Expense deleted.'));
    }

    /** The receipt file, only through this authorized, farm-scoped route (never a public URL). */
    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->receipt_path && Storage::disk(ReceiptStorage::DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(ReceiptStorage::DISK)->response($expense->receipt_path);
    }

    public function export(ExpenseIndexRequest $request): BinaryFileResponse|RedirectResponse
    {
        $filters = $request->filters();
        $rows = $this->expenses->totals($this->expenses->applyFilters(Expense::query(), $filters))['count'];

        return Exporter::download(
            new ExpensesExport($this->currentFarm->id(), $filters),
            $rows,
            'expenses',
            __('Expenses export'),
            $request->user(),
        );
    }

    /**
     * Lists for the filter bar. Vendors are not listed (remote Select2); only the chosen one is loaded.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function filterOptions(array $filters): array
    {
        return [
            'categories' => ExpenseCategory::query()->topLevel()->ordered()->pluck('name', 'id'),
            'subcategories' => $filters['category_id']
                ? ExpenseCategory::query()->where('parent_id', $filters['category_id'])->ordered()->pluck('name', 'id')
                : collect(),
            'sections' => Section::query()->orderBy('name')->pluck('name', 'id'),
            'paymentModes' => PaymentMode::query()->orderBy('name')->pluck('name', 'id'),
            'selectedVendor' => $filters['vendor_id'] ? Vendor::withTrashed()->whereKey($filters['vendor_id'])->pluck('name', 'id') : collect(),
            'users' => User::query()->whereHas('farms', fn ($q) => $q->whereKey($this->currentFarm->id()))->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(Expense $expense): array
    {
        $activeOr = fn ($query, ?int $current) => $query->where(fn ($q) => $q->where('is_active', true)
            ->when($current, fn ($q) => $q->orWhere('id', $current)));

        $categoryId = old('category_id', $expense->category_id);

        return [
            'expense' => $expense,
            'categories' => $activeOr(ExpenseCategory::query()->topLevel(), $expense->category_id)->ordered()->pluck('name', 'id'),
            'subcategories' => $categoryId
                ? $activeOr(ExpenseCategory::query()->where('parent_id', $categoryId), $expense->subcategory_id)->ordered()->pluck('name', 'id')
                : collect(),
            'sections' => $activeOr(Section::query(), $expense->section_id)->orderBy('name')->pluck('name', 'id'),
            'paymentModes' => $activeOr(PaymentMode::query(), $expense->payment_mode_id)->orderBy('name')->pluck('name', 'id'),
            'selectedVendor' => ($vendorId = old('vendor_id', $expense->vendor_id))
                ? Vendor::withTrashed()->whereKey($vendorId)->pluck('name', 'id')
                : collect(),
            'units' => ExpenseUnit::options(),
        ];
    }
}
