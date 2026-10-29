@extends('layouts.app')

@section('title', __('Routes'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('delivery-routes.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add route') }}
        </a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('delivery-routes.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#routes-results">
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" maxlength="60"
                       class="form-control form-control-sm" placeholder="{{ __('Route name') }}">
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('delivery-routes.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="routes-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-end" style="width: 5rem">{{ __('Order') }}</th>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Delivery person') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($routes as $route)
                    <tr>
                        <td class="text-end text-body-secondary">{{ $route->sort_order }}</td>
                        <td class="fw-semibold">{{ $route->name }}</td>
                        <td>
                            @if ($route->deliveryUser)
                                <i class="bi bi-person me-1 text-body-secondary"></i>{{ $route->deliveryUser->name }}
                            @else
                                <span class="text-body-secondary">{{ __('Not assigned') }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($route->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('delivery-routes.edit', $route) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 5 : 4 }}" class="text-center text-body-secondary py-5">
                            {{ array_filter($filters) ? __('No routes match these filters.') : __('No routes yet. Add one for each delivery round, e.g. "Station Road – Morning".') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($routes->hasPages())<div class="card-footer">{{ $routes->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Routes are never deleted — make one inactive to stop using it; its customers and past deliveries keep it.') }}</p>
@endsection
