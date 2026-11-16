@extends('layouts.app')

@section('title', __('Animal purchases'))

@php($canManage = auth()->user()->can('animal_trading.manage'))
@php($filtered = $filters['search'] !== '' || $filters['from'] !== '' || $filters['to'] !== '')

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('animal-purchases.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New purchase') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('animal-purchases.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#purchases-results">
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Purchase no. or party') }}">
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('animal-purchases.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="purchases-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Purchase no.') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th class="text-end">{{ __('Animals') }}</th>
                    <th class="text-end">{{ __('Animal price') }}</th>
                    <th class="text-end">{{ __('Total amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td><a href="{{ route('animal-purchases.show', $purchase) }}" class="fw-semibold">{{ $purchase->purchase_no }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($purchase->purchase_date) }}</td>
                        <td>
                            {{ $purchase->party->name }}
                            @if ($purchase->party->village)<div class="small text-body-secondary">{{ $purchase->party->village }}</div>@endif
                        </td>
                        <td class="text-end">{{ $purchase->items_count }}</td>
                        <td class="text-end"><x-money :value="$purchase->total_animal_price" /></td>
                        <td class="text-end fw-semibold"><x-money :value="$purchase->total_amount" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-cart fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No purchases match these filters.') : __('No animal purchases yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('animal-purchases.create') }}" class="btn btn-primary btn-sm">{{ __('Record first purchase') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($purchases->hasPages())<div class="card-footer">{{ $purchases->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
