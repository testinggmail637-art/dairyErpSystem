@extends('layouts.app')

@section('title', __('Bills'))

@section('page-actions')
    @can('bills.generate')
        <a href="{{ route('bills.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Generate bills') }}</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('bills.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#bills-results">
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="{{ __('Bill no., customer name, code or phone') }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="month" class="form-label small mb-1">{{ __('Bill month') }}</label>
                <input type="month" id="month" name="month" value="{{ $filters['month'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\BillStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('bills.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="bills-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Bill') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Bill date') }}</th>
                    <th class="text-end">{{ __('This bill') }}</th>
                    <th class="text-end">{{ __('Closing balance') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bills as $bill)
                    <tr>
                        <td><a href="{{ route('bills.show', $bill) }}" class="fw-semibold text-nowrap">{{ $bill->label() }}</a></td>
                        <td>{{ $bill->customer->name }} <span class="small font-monospace text-body-secondary">{{ $bill->customer->code }}</span></td>
                        <td class="small text-nowrap">{{ fmt_date($bill->period_from) }} – {{ fmt_date($bill->period_to) }}</td>
                        <td class="text-nowrap">{{ fmt_date($bill->bill_date) }}</td>
                        <td class="text-end"><x-money :value="$bill->total" /></td>
                        <td class="text-end">@if ($bill->status === \App\Enums\BillStatus::Finalized)<x-money :value="$bill->closing_balance" />@else — @endif</td>
                        <td class="text-nowrap">
                            <span class="badge {{ $bill->status->badge() }}">{{ $bill->status->label() }}</span>
                            @if ($paid = $bill->paidStatus())<span class="badge {{ $paid->badge() }}">{{ $paid->label() }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">
                        <i class="bi bi-receipt fs-2 d-block mb-2"></i>{{ array_filter($filters) ? __('No bills match these filters.') : __('No bills yet.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($bills->hasPages())<div class="card-footer">{{ $bills->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
