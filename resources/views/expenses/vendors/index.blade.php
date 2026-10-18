@extends('layouts.app')

@section('title', __('Vendors'))

@php($canManage = auth()->user()->can('vendors.manage'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('vendors.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add vendor') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('vendors.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#vendors-results">
            <div class="col-12 col-md-6">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Vendor name or phone') }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('vendors.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="vendors-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Vendor') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('GSTIN') }}</th>
                    <th class="text-end">{{ __('We owe') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($vendors as $vendor)
                    <tr>
                        <td>
                            <a href="{{ route('vendors.show', $vendor) }}" class="fw-semibold">{{ $vendor->name }}</a>
                            @if ($vendor->address)<div class="small text-body-secondary">{{ $vendor->address }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $vendor->phone ?: '—' }}</td>
                        <td><span class="font-monospace small">{{ $vendor->gstin ?: '—' }}</span></td>
                        <td class="text-end"><x-money :value="$vendor->balance()" /></td>
                        <td>
                            @if ($vendor->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                                <x-confirm-form :action="route('vendors.destroy', $vendor)"
                                                :message="__('Delete the vendor :name?', ['name' => $vendor->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-shop fs-2 d-block mb-2"></i>
                            {{ $filters['search'] !== '' || $filters['status'] !== '' ? __('No vendors match these filters.') : __('No vendors yet.') }}
                            @if ($canManage && $filters['search'] === '' && $filters['status'] === '')
                                <div class="mt-2"><a href="{{ route('vendors.create') }}" class="btn btn-primary btn-sm">{{ __('Add first vendor') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($vendors->hasPages())<div class="card-footer">{{ $vendors->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('We owe = opening balance + credit (unpaid) purchases − payments. Open a vendor to see the ledger and record a payment.') }}</p>
@endsection
