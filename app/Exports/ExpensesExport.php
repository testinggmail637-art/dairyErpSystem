<?php

namespace App\Exports;

use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Expense list → Excel with the same filters as the screen. Reads in chunks (FromQuery), so it also works
 * in a queued job for large ranges; the farm is passed in because a job has no logged-in user / current farm.
 */
class ExpensesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(private readonly int $farmId, private readonly array $filters) {}

    /**
     * @return Builder<Expense>
     */
    public function query(): Builder
    {
        $query = Expense::allFarms()
            ->where('farm_id', $this->farmId)
            ->with(['category:id,name', 'subcategory:id,name', 'section:id,name', 'vendor:id,name', 'paymentMode:id,name', 'creator:id,name'])
            ->orderBy('expense_date')
            ->orderBy('id');

        return app(ExpenseService::class)->applyFilters($query, $this->filters);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            __('Date'), __('Category'), __('Subcategory'), __('Section'), __('Vendor / paid to'), __('Paid / credit'),
            __('Payment mode'), __('Quantity'), __('Unit'), __('Amount (₹)'), __('Note'), __('Entered by'), __('Entered on'),
        ];
    }

    /**
     * @param  Expense  $expense
     * @return list<mixed>
     */
    public function map($expense): array
    {
        return [
            Format::date($expense->expense_date),
            $expense->category?->name,
            $expense->subcategory?->name,
            $expense->section?->name,
            $expense->payee(),
            $expense->is_credit ? __('Credit') : __('Paid'),
            $expense->paymentMode?->name,
            $expense->quantity,          // decimal string → stored as a number by the spreadsheet writer
            $expense->unit?->label(),
            $expense->amount,
            $expense->note,
            $expense->creator?->name,
            Format::dateTime($expense->created_at),
        ];
    }
}
