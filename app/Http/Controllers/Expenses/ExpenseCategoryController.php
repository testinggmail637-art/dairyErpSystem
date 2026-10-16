<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expenses → Categories: tree of categories and their subcategories.
 */
class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $categories) {}

    /** The whole tree on one page: a small, bounded master list (two queries, no pagination needed). */
    public function index(): View
    {
        return view('expenses.categories.index', [
            'categories' => ExpenseCategory::query()->topLevel()->ordered()->with('children')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('expenses.categories.form', [
            'category' => new ExpenseCategory([
                'is_active' => true,
                'parent_id' => $request->integer('parent') ?: null,
            ]),
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        $category = $this->categories->create($request->validated());

        return redirect()->route('expense-categories.index')
            ->with('success', __(':name added.', ['name' => $category->name]));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('expenses.categories.form', [
            'category' => $expenseCategory,
            'parents' => $this->parentOptions($expenseCategory),
        ]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->categories->update($expenseCategory, $request->validated());

        return redirect()->route('expense-categories.index')
            ->with('success', __(':name updated.', ['name' => $expenseCategory->name]));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        try {
            $this->categories->delete($expenseCategory);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('expense-categories.index')
            ->with('success', __(':name deleted.', ['name' => $expenseCategory->name]));
    }

    /**
     * Active subcategories of a category for the dependent dropdown (expense form and filters).
     * {"12": "Cattle Feed", "13": "Silage"}
     */
    public function children(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        abort_unless($request->user()->canAny(['expenses.view', 'expenses.create']), 403);

        return response()->json(
            $expenseCategory->children()->where('is_active', true)->pluck('name', 'id')
        );
    }

    /**
     * Main categories a subcategory can belong to (never the category itself).
     *
     * @return array<int, string>
     */
    private function parentOptions(?ExpenseCategory $except = null): array
    {
        return ExpenseCategory::query()->topLevel()->ordered()
            ->when($except?->exists, fn ($q) => $q->whereKeyNot($except->id))
            ->pluck('name', 'id')
            ->all();
    }
}
