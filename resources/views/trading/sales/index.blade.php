@extends('layouts.app')

@section('title', __('Animal sales'))

@php($canManage = auth()->user()->can('animal_trading.manage'))
@php($filtered = $filters['search'] !== '' || $filters['from'] !== '' || $filters['to'] !== '' || $filters['status'] !== '')

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('animal-sales.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New sale') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('animal-sales.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#sales-results">
            <div class="col-12 col-md-2">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Sale no. or party') }}">
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\AnimalSaleStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('animal-sales.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="sales-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Sale no.') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th class="text-end">{{ __('Animals') }}</th>
                    <th class="text-end">{{ __('Sale price') }}</th>
                    <th class="text-end">{{ __('Net amount') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td><a href="{{ route('animal-sales.show', $sale) }}" class="fw-semibold">{{ $sale->sale_no }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($sale->sale_date) }}</td>
                        <td>
                            {{ $sale->party->name }}
                            @if ($sale->party->village)<div class="small text-body-secondary">{{ $sale->party->village }}</div>@endif
                        </td>
                        <td class="text-end">{{ $sale->items_count }}</td>
                        <td class="text-end"><x-money :value="$sale->total_price" /></td>
                        <td class="text-end fw-semibold"><x-money :value="$sale->net_amount" /></td>
                        <td>
                            <span class="badge {{ $sale->isCancelled() ? 'text-bg-secondary' : 'text-bg-success' }}">{{ $sale->status->label() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-cash-coin fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No sales match these filters.') : __('No animal sales yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('animal-sales.create') }}" class="btn btn-primary btn-sm">{{ __('Record first sale') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($sales->hasPages())<div class="card-footer">{{ $sales->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
