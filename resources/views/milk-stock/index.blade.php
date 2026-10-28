@extends('layouts.app')

@section('title', __('Milk stock'))

@section('page-actions')
    <a href="{{ route('milk-stock.ledger') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text me-1"></i>{{ __('Ledger') }}</a>
    <a href="{{ route('milk-stock.usage.create', ['date' => $date]) }}" class="btn btn-primary btn-sm"><i class="bi bi-droplet-half me-1"></i>{{ __('Record usage') }}</a>
    @can('milk_stock.adjust')
        <a href="{{ route('milk-stock.adjustments.create', ['date' => $date]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-sliders me-1"></i>{{ __('Adjustment') }}</a>
    @endcan
@endsection

@section('content')
@php
    $prev = \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString();
    $next = \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString();
    $columns = [
        'opening' => __('Opening'), 'in' => __('In'), 'delivered' => __('Delivered'), 'wastage' => __('Wastage'),
        'home_use' => __('Home use'), 'calf_feeding' => __('Calf feeding'), 'adjustment' => __('Adjustment'), 'closing' => __('Closing'),
    ];
@endphp

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('milk-stock.index') }}" class="d-flex flex-wrap gap-2 align-items-end" id="stock-date-form">
            <a href="{{ route('milk-stock.index', ['date' => $prev]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-left"></i></a>
            <div>
                <label for="date" class="form-label small mb-1">{{ __('Date') }}</label>
                <x-form.date name="date" :value="$date" max="today" group-class="mb-0" />
            </div>
            @if ($date < today()->toDateString())
                <a href="{{ route('milk-stock.index', ['date' => $next]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
            @endif
        </form>
    </div>
</div>

{{-- Daily stock sheet (spec 7.4): litres per source × milk type and combined --}}
<div class="card mb-3">
    <div class="card-header fw-semibold">{{ __('Stock sheet of :date (litres)', ['date' => fmt_date($date)]) }}</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="stock-sheet">
            <thead>
                <tr>
                    <th>{{ __('Stock') }}</th>
                    @foreach ($columns as $label)
                        <th class="text-end text-nowrap">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['rows'] as $key => $row)
                    <tr>
                        <td class="text-nowrap">
                            <a href="{{ route('milk-stock.ledger', ['source' => $row['source']->value, 'milk_type' => $row['milk_type']->value, 'from' => $date, 'to' => $date]) }}" class="text-decoration-none">
                                {{ $row['source']->label() }} · {{ $row['milk_type']->label() }}
                            </a>
                        </td>
                        @foreach ($columns as $column => $label)
                            <td class="text-end text-nowrap {{ $column === 'closing' ? 'fw-semibold' : '' }} {{ \App\Support\Money::isNegative($row[$column]) ? 'text-danger' : '' }}">{{ fmt_qty($row[$column]) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td>{{ __('Combined') }}</td>
                    @foreach ($columns as $column => $label)
                        <td class="text-end text-nowrap {{ \App\Support\Money::isNegative($summary['combined'][$column]) ? 'text-danger' : '' }}">{{ fmt_qty($summary['combined'][$column]) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer small text-body-secondary">
        {{ __('In = own production and collection. Adjustment is shown net (in − out). Closing = opening + in − delivered − wastage − home use − calf feeding + adjustment.') }}
    </div>
</div>

{{-- The day's usage and adjustments, with their reasons --}}
<div class="card">
    <div class="card-header fw-semibold">{{ __('Usage and adjustments of :date', ['date' => fmt_date($date)]) }}</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Stock') }}</th>
                    <th class="text-end">{{ __('Litres') }}</th>
                    <th>{{ __('Reason') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Entered by') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($manual as $movement)
                    <tr>
                        <td class="text-nowrap">{{ $movement->movement_type->label() }}@if ($movement->shift) <span class="small text-body-secondary">· {{ $movement->shift->label() }}</span>@endif</td>
                        <td class="text-nowrap">{{ $movement->stock_source->label() }} · {{ $movement->milk_type->label() }}</td>
                        <td class="text-end fw-semibold text-nowrap">
                            {{ $movement->movement_type->isIn() ? '+'.fmt_qty($movement->quantity_in) : '−'.fmt_qty($movement->quantity_out) }}
                        </td>
                        <td>{{ $movement->reason }}</td>
                        <td class="d-none d-md-table-cell small">{{ $movement->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No usage or adjustments on this day.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/milk-stock.js') }}"></script>
@endpush
