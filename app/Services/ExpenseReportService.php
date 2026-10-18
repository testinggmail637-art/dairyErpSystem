<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Section;
use App\Models\Vendor;
use App\Reports\ReportTable;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Expense reports (spec 7.1): daily summary, monthly category × section, category-wise for a period, vendor
 * outstanding. Every figure is a SQL aggregate over the farm-scoped, non-deleted expenses — rows are never
 * loaded into PHP — so a report's totals always equal the sum of the expenses it covers.
 */
class ExpenseReportService
{
    public const REPORTS = ['daily', 'monthly', 'category', 'vendors'];

    public function daily(string $from, string $to): ReportTable
    {
        $rows = Expense::query()->toBase()
            ->whereBetween('expense_date', [$from, $to])
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->selectRaw('expense_date, COUNT(*) AS cnt, SUM(amount) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_credit = 0 THEN amount END), 0) AS paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_credit = 1 THEN amount END), 0) AS credit')
            ->get();

        return new ReportTable(
            __('Daily expense summary'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Date'), ReportTable::DATE),
                ReportTable::column(__('Entries'), ReportTable::NUMBER),
                ReportTable::column(__('Paid'), ReportTable::MONEY),
                ReportTable::column(__('Credit'), ReportTable::MONEY),
                ReportTable::column(__('Total'), ReportTable::MONEY),
            ],
            $rows->map(fn ($r) => [$r->expense_date, (int) $r->cnt, Money::round2((string) $r->paid), Money::round2((string) $r->credit), Money::round2((string) $r->total)])->all(),
            [__('Total'), $rows->sum('cnt'), Money::sum($rows->pluck('paid')), Money::sum($rows->pluck('credit')), Money::sum($rows->pluck('total'))],
        );
    }

    /** Category (rows) × section (columns) for one month, with row and column totals. */
    public function monthly(string $month): ReportTable
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $cells = Expense::query()->toBase()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('category_id', 'section_id')
            ->selectRaw('category_id, section_id, SUM(amount) AS total')
            ->get();

        $sections = Section::query()->whereIn('id', $cells->pluck('section_id')->unique())->orderBy('name')->pluck('name', 'id');
        $categories = ExpenseCategory::query()->whereIn('id', $cells->pluck('category_id')->unique())->ordered()->pluck('name', 'id');
        $byCell = $cells->keyBy(fn ($c) => $c->category_id.'-'.$c->section_id);

        $rows = $categories->map(function ($name, $categoryId) use ($sections, $byCell) {
            $amounts = $sections->keys()->map(fn ($sectionId) => Money::round2((string) ($byCell->get($categoryId.'-'.$sectionId)?->total ?? '0')));

            return [$name, ...$amounts->all(), Money::sum($amounts)];
        })->values();

        $columnTotals = $sections->keys()->map(fn ($sectionId) => Money::sum($cells->where('section_id', $sectionId)->pluck('total')));

        return new ReportTable(
            __('Monthly expenses by category and section'),
            $from->translatedFormat('F Y'),
            [
                ReportTable::column(__('Category')),
                ...$sections->map(fn ($name) => ReportTable::column($name, ReportTable::MONEY))->values()->all(),
                ReportTable::column(__('Total'), ReportTable::MONEY),
            ],
            $rows->all(),
            [__('Total'), ...$columnTotals->all(), Money::sum($cells->pluck('total'))],
        );
    }

    /** Category → subcategory totals for a period, with each line's share of the grand total. */
    public function categoryWise(string $from, string $to): ReportTable
    {
        $lines = Expense::query()->toBase()
            ->whereBetween('expense_date', [$from, $to])
            ->groupBy('category_id', 'subcategory_id')
            ->selectRaw('category_id, subcategory_id, COUNT(*) AS cnt, SUM(amount) AS total')
            ->get();

        $names = ExpenseCategory::query()->whereIn('id', $lines->pluck('category_id')->merge($lines->pluck('subcategory_id'))->filter()->unique())
            ->pluck('name', 'id');
        $order = ExpenseCategory::query()->topLevel()->ordered()->pluck('id')->flip();
        $grand = Money::sum($lines->pluck('total'));
        $share = fn (string $amount) => Money::isZero($grand) ? '0.0' : Money::round(bcmul(bcdiv($amount, $grand, 6), '100', 4), 1);

        $rows = [];

        foreach ($lines->groupBy('category_id')->sortBy(fn ($g, $id) => $order[$id] ?? PHP_INT_MAX) as $categoryId => $group) {
            $categoryTotal = Money::sum($group->pluck('total'));
            $rows[] = [$names[$categoryId] ?? '—', '', (int) $group->sum('cnt'), $categoryTotal, $share($categoryTotal)];

            foreach ($group->sortBy(fn ($l) => $names[$l->subcategory_id] ?? '') as $line) {
                $amount = Money::round2((string) $line->total);
                $rows[] = ['', $line->subcategory_id ? ($names[$line->subcategory_id] ?? '—') : __('(no subcategory)'), (int) $line->cnt, $amount, $share($amount)];
            }
        }

        return new ReportTable(
            __('Category-wise expenses'),
            $this->period($from, $to),
            [
                ReportTable::column(__('Category')),
                ReportTable::column(__('Subcategory')),
                ReportTable::column(__('Entries'), ReportTable::NUMBER),
                ReportTable::column(__('Amount'), ReportTable::MONEY),
                ReportTable::column(__('Share'), ReportTable::PERCENT),
            ],
            $rows,
            [__('Total'), '', (int) $lines->sum('cnt'), $grand, $lines->isEmpty() ? '0.0' : '100.0'],
        );
    }

    /** Vendors we owe money to (or who hold our advance), largest balance first. */
    public function vendorOutstanding(): ReportTable
    {
        // balance = opening + credit expenses − payments, computed per vendor in SQL (Vendor::withBalance).
        $vendors = Vendor::query()->withBalance()->get(['id', 'name', 'phone', 'opening_balance', 'is_active'])
            ->map(fn (Vendor $v) => ['vendor' => $v, 'balance' => $v->balance()])
            ->reject(fn (array $r) => Money::isZero($r['balance']))
            ->sort(fn ($a, $b) => Money::compare($b['balance'], $a['balance']))
            ->values();

        return new ReportTable(
            __('Vendor outstanding'),
            __('As on :date', ['date' => fmt_date(now())]),
            [
                ReportTable::column(__('Vendor')),
                ReportTable::column(__('Phone')),
                ReportTable::column(__('Opening'), ReportTable::MONEY),
                ReportTable::column(__('Credit purchases'), ReportTable::MONEY),
                ReportTable::column(__('Paid'), ReportTable::MONEY),
                ReportTable::column(__('We owe'), ReportTable::MONEY),
            ],
            $vendors->map(fn (array $r) => [
                $r['vendor']->name.($r['vendor']->is_active ? '' : ' ('.__('inactive').')'),
                $r['vendor']->phone,
                Money::round2($r['vendor']->opening_balance),
                Money::round2((string) ($r['vendor']->credit_total ?? '0')),
                Money::round2((string) ($r['vendor']->paid_total ?? '0')),
                $r['balance'],
            ])->all(),
            [__('Total'), '', Money::sum($vendors->map(fn ($r) => $r['vendor']->opening_balance)),
                Money::sum($vendors->map(fn ($r) => $r['vendor']->credit_total ?? '0')),
                Money::sum($vendors->map(fn ($r) => $r['vendor']->paid_total ?? '0')),
                Money::sum($vendors->pluck('balance'))],
            [__('Minus amounts are advances the vendor holds. Vendors with a zero balance are not listed.')],
        );
    }

    private function period(string $from, string $to): string
    {
        return fmt_date($from).' – '.fmt_date($to);
    }
}
