@extends('layouts.app')

@section('title', __('Customers'))

@php($canManage = auth()->user()->can('customers.manage'))
@php($filtered = array_filter($filters) !== [])

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add customer') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('customers.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#customers-results">
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Name, code or phone') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="type" class="form-label small mb-1">{{ __('Type') }}</label>
                <select id="type" name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\CustomerType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="route_id" class="form-label small mb-1">{{ __('Route') }}</label>
                <select id="route_id" name="route_id" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="none" @selected($filters['route_id'] === 'none')>{{ __('No route') }}</option>
                    @foreach ($routes as $id => $name)
                        <option value="{{ $id }}" @selected($filters['route_id'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('customers.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="customers-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="font-monospace small text-nowrap">{{ $customer->code }}</td>
                        <td>
                            <a href="{{ route('customers.show', $customer) }}" class="fw-semibold">{{ $customer->name }}</a>
                            @if ($customer->area)<div class="small text-body-secondary">{{ $customer->area }}</div>@endif
                        </td>
                        <td>
                            {{ $customer->customer_type->label() }}
                            @if ($customer->b2b_category)<div class="small text-body-secondary">{{ $customer->b2b_category->label() }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $customer->phone ?: '—' }}</td>
                        <td class="text-nowrap">
                            {{ $customer->route?->name ?? '—' }}
                            @if ($customer->route && $customer->route_sequence)<span class="small text-body-secondary">#{{ $customer->route_sequence }}</span>@endif
                        </td>
                        <td>
                            @if ($customer->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No customers match these filters.') : __('No customers yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">{{ __('Add first customer') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($customers->hasPages())<div class="card-footer">{{ $customers->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
