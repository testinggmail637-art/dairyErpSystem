<?php

namespace App\Exports\ExpenseTemplate;

use App\Enums\ExpenseUnit;
use App\Models\ExpenseCategory;
use App\Models\PaymentMode;
use App\Models\Section;
use App\Models\Vendor;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * expenses_template.xlsx (spec 7.2): sheet 1 "Expenses" with the header row (and dropdowns), sheet 2 "Lists"
 * with the current farm's active categories, subcategories, sections, payment modes and vendors.
 */
class ExpenseTemplateExport implements WithMultipleSheets
{
    /** Rows that get dropdown validation (the importer accepts up to 5,000 rows). */
    public const ROWS = 5000;

    /** @return list<object> */
    public function sheets(): array
    {
        $lists = $this->lists();

        return [
            new ExpenseTemplateSheet($lists),
            new ExpenseListsSheet($lists),
        ];
    }

    /**
     * Each list is one column on the Lists sheet.
     *
     * @return array<string, list<string>>
     */
    private function lists(): array
    {
        $categories = ExpenseCategory::query()->topLevel()->active()->ordered()->get(['id', 'name']);
        $subcategories = ExpenseCategory::query()->active()
            ->whereIn('parent_id', $categories->pluck('id'))
            ->ordered()->get(['parent_id', 'name']);
        $categoryNames = $categories->pluck('name', 'id');

        return [
            'Categories' => $categories->pluck('name')->all(),
            // "Feed › Cattle Feed": which subcategory belongs to which category
            'Subcategories (category › subcategory)' => $subcategories
                ->sortBy(fn ($s) => [array_search($s->parent_id, $categories->pluck('id')->all(), true), $s->name])
                ->map(fn ($s) => $categoryNames[$s->parent_id].' › '.$s->name)->values()->all(),
            'Sections' => Section::query()->active()->orderBy('name')->pluck('name')->all(),
            'Payment modes' => PaymentMode::query()->active()->orderBy('name')->pluck('name')->all(),
            'Vendors' => Vendor::query()->active()->orderBy('name')->pluck('name')->all(),
            'Units' => array_keys(ExpenseUnit::options()),
            'Credit' => ['no', 'yes'],
        ];
    }
}
