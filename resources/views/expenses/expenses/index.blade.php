@extends('layouts.app')

@section('title', $ownOnly ? __('My expense entries') : __('Expenses'))

@section('page-actions')
    @can('expenses.create')
        <a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add expense') }}</a>
    @endcan
@endsection

@section('content')
<div class="card">
    @if ($ownOnly)
        <div class="card-body border-bottom small text-body-secondary">
            <i class="bi bi-person-check me-1"></i>{{ __('Your own entries that you can still change. Entries can be edited or deleted on the day they were made.') }}
        </div>
    @else
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#expenses-results">
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="category_id" :label="__('Category')" :options="$categories" :selected="$filters['category_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="subcategory_id" :label="__('Subcategory')" :options="$subcategories" :selected="$filters['subcategory_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm"
                               data-children-of="#category_id" :data-children-url="route('expense-categories.children', '__ID__')"
                               :disabled="! $filters['category_id']" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="section_id" :label="__('Section')" :options="$sections" :selected="$filters['section_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="vendor_id" :label="__('Vendor')" :options="$selectedVendor" :selected="$filters['vendor_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('vendors.search')" group-class="mb-0" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="payment_mode_id" :label="__('Payment mode')" :options="$paymentModes" :selected="$filters['payment_mode_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="type" :label="__('Paid / credit')" :options="['paid' => __('Paid'), 'credit' => __('Credit (unpaid)')]"
                               :selected="$filters['type']" :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="created_by" :label="__('Entered by')" :options="$users" :selected="$filters['created_by']"
                               :placeholder="__('Anyone')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="search" class="form-label small mb-1">{{ __('Paid to / note') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" maxlength="100">
            </div>
            <div class="col-12 col-xl-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('expenses.index', ['all' => 1]) }}" class="btn btn-link btn-sm" data-ajax-clear title="{{ __('All dates, no filters') }}">{{ __('Clear') }}</a>
            </div>
        </form>
        @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
    </div>
    @endif

    <div id="expenses-results" data-ajax-results>
    @fragment('results')
    {{-- Totals of ALL filtered expenses (one SQL query), not only this page. --}}
    <div class="d-flex flex-wrap align-items-center gap-3 px-3 py-2 border-bottom app-totals">
        <div><span class="small text-body-secondary">{{ __('Expenses') }}</span> <strong>{{ fmt_qty($totals['count'], 0) }}</strong></div>
        <div><span class="small text-body-secondary">{{ __('Total') }}</span> <strong><x-money :value="$totals['total']" /></strong></div>
        <div><span class="small text-body-secondary">{{ __('Paid') }}</span> <x-money :value="$totals['paid']" /></div>
        <div><span class="small text-body-secondary">{{ __('Credit') }}</span> <x-money :value="$totals['credit']" /></div>
        @unless ($ownOnly)
            <a href="{{ route('expenses.export', array_filter($filters, fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-outline-success btn-sm ms-auto">
                <i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel') }}
            </a>
        @endunless
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Section') }}</th>
                    <th>{{ __('Vendor / paid to') }}</th>
                    <th>{{ __('Mode') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Entered by') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($expense->expense_date) }}</td>
                        <td>
                            {{ $expense->category?->name }}
                            @if ($expense->subcategory)<div class="small text-body-secondary">{{ $expense->subcategory->name }}</div>@endif
                        </td>
                        <td>{{ $expense->section?->name }}</td>
                        <td>
                            {{ $expense->payee() ?? '—' }}
                            @if ($expense->receipt_path)<i class="bi bi-paperclip text-body-secondary" title="{{ __('Receipt attached') }}"></i>@endif
                        </td>
                        <td>
                            @if ($expense->is_credit)
                                <span class="badge text-bg-warning">{{ __('Credit') }}</span>
                            @else
                                <span class="small">{{ $expense->paymentMode?->name }}</span>
                            @endif
                        </td>
                        <td class="text-end"><x-money :value="$expense->amount" /></td>
                        <td class="d-none d-lg-table-cell small">{{ $expense->creator?->name }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('expenses.show', $expense) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                            @can('update', $expense)
                                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline-primary btn-sm" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @elseif ($lock = \App\Policies\ExpensePolicy::lockedBecause($expense))
                                <span class="btn btn-sm text-body-secondary" title="{{ $lock }}"><i class="bi bi-lock-fill"></i></span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-5">
                            <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                            {{ __('No expenses for these filters.') }}
                            @can('expenses.create')
                                <div class="mt-2"><a href="{{ route('expenses.create') }}" class="btn btn-primary btn-sm">{{ __('Add an expense') }}</a></div>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($expenses->count() > 1)
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="5" class="text-end">{{ __('This page') }}</td>
                        <td class="text-end"><x-money :value="\App\Support\Money::sum($expenses->getCollection()->pluck('amount'))" /></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @if ($expenses->hasPages())<div class="card-footer">{{ $expenses->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
