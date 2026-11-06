@extends('layouts.app')

@section('title', __('Deliveries'))

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="GET" action="{{ route('deliveries.index') }}" class="d-flex gap-2 align-items-end">
            <x-form.date name="date" :label="__('Date')" :value="$date" group-class="mb-0" />
            <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
        </form>
        <a href="{{ route('deliveries.sheet', ['date' => $date]) }}" class="btn btn-success"><i class="bi bi-list-check me-1"></i>{{ __('Open delivery sheet') }}</a>
        @if ($canGenerate)
            <form method="POST" action="{{ route('deliveries.generate') }}" class="ms-md-auto">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Generate sheet') }}</button>
            </form>
        @endif
    </div>
    @if ($canGenerate)
        <div class="card-footer small text-body-secondary">
            {{ __('Generate sheet adds a pending delivery for every active subscription on this date (not paused). Rows already on the sheet are not changed, so it is safe to press again after adding subscriptions. The sheet for today is also made automatically every morning.') }}
        </div>
    @endif
</div>

<div class="card">
    <div class="card-header fw-semibold">{{ __('Sheet for :date', ['date' => fmt_date($date)]) }}</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th class="text-end">{{ __('Customers') }}</th>
                    <th class="text-end">{{ __('Planned (L)') }}</th>
                    <th class="text-end">{{ __('Pending') }}</th>
                    <th class="text-end">{{ __('Delivered') }}</th>
                    <th class="text-end">{{ __('Skipped') }}</th>
                    <th class="text-end">{{ __('Delivered (L)') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('deliveries.sheet', array_filter(['date' => $date, 'shift' => $row->shift->value, 'route_id' => $row->route_id])) }}">
                                {{ $row->route_id ? ($routes[$row->route_id] ?? '—') : __('No route') }}
                            </a>
                        </td>
                        <td>{{ $row->shift->label() }}</td>
                        <td class="text-end">{{ $row->total }}</td>
                        <td class="text-end">{{ fmt_qty($row->planned_qty) }}</td>
                        <td class="text-end">{{ $row->pending }}</td>
                        <td class="text-end">{{ $row->delivered }}</td>
                        <td class="text-end">{{ $row->skipped }}</td>
                        <td class="text-end">{{ fmt_qty($row->delivered_qty) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-5">
                        <i class="bi bi-truck fs-2 d-block mb-2"></i>{{ __('No deliveries on this date yet.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
