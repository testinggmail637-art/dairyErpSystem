@extends('layouts.app')

@section('title', __('Subscriptions'))

@php($filtered = array_filter($filters) !== [])

@section('page-actions')
    <a href="{{ route('subscriptions.create', array_filter(['customer_id' => $filters['customer_id']])) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Add subscription') }}
    </a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('subscriptions.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#subscriptions-results">
            @if ($filters['customer_id'] !== '')<input type="hidden" name="customer_id" value="{{ $filters['customer_id'] }}">@endif
            <div class="col-12 col-md-3">
                <label for="search" class="form-label small mb-1">{{ __('Customer') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Name, code or phone') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="route_id" class="form-label small mb-1">{{ __('Route') }}</label>
                <select id="route_id" name="route_id" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($routes as $id => $name)
                        <option value="{{ $id }}" @selected($filters['route_id'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="product_id" class="form-label small mb-1">{{ __('Product') }}</label>
                <select id="product_id" name="product_id" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($products as $id => $name)
                        <option value="{{ $id }}" @selected($filters['product_id'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label for="shift" class="form-label small mb-1">{{ __('Shift') }}</label>
                <select id="shift" name="shift" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\MilkShift::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['shift'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\SubscriptionStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('subscriptions.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="subscriptions-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th class="text-end">{{ __('Litres / day') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $sub)
                    <tr>
                        <td>
                            <a href="{{ route('customers.show', $sub->customer_id) }}" class="fw-semibold">{{ $sub->customer->name }}</a>
                            <div class="small text-body-secondary font-monospace">{{ $sub->customer->code }}</div>
                        </td>
                        <td class="text-nowrap">{{ $sub->customer->route?->name ?? '—' }}</td>
                        <td>
                            {{ $sub->product->name }}
                            @if ($sub->stock_source === \App\Enums\MilkSource::Collection)<div class="small text-body-secondary">{{ $sub->stock_source->label() }}</div>@endif
                        </td>
                        <td>{{ $sub->shift->label() }}</td>
                        <td class="text-end">{{ fmt_qty($sub->quantity) }}</td>
                        <td class="text-nowrap small">{{ fmt_date($sub->start_date) }} – {{ $sub->end_date ? fmt_date($sub->end_date) : __('ongoing') }}</td>
                        <td>
                            @if ($sub->status === \App\Enums\SubscriptionStatus::Stopped)
                                <span class="badge text-bg-secondary">{{ __('Stopped') }}</span>
                            @elseif ($sub->paused_today)
                                <span class="badge text-bg-warning">{{ __('Paused today') }}</span>
                            @else
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('subscriptions.edit', $sub) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit / pause') }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-5">
                            <i class="bi bi-calendar-check fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No subscriptions match these filters.') : __('No subscriptions yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($subscriptions->hasPages())<div class="card-footer">{{ $subscriptions->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
