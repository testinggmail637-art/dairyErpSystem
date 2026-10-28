@extends('layouts.app')

@section('title', __('Milk stock ledger'))

@section('page-actions')
    <a href="{{ route('milk-stock.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>{{ __('Stock sheet') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('milk-stock.ledger') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#stock-ledger">
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="source" :label="__('Stock')" :options="\App\Enums\MilkSource::options()" :selected="$filters['source']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="milk_type" :label="__('Milk type')" :options="\App\Enums\MilkType::options()" :selected="$filters['milk_type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="movement_type" :label="__('Movement')" :options="\App\Enums\MilkMovementType::options()" :selected="$filters['movement_type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('milk-stock.ledger') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="stock-ledger" data-ajax-results>
    @fragment('results')
    @php $rows = $ledger['rows']; @endphp
    <div class="card-body border-bottom d-flex flex-wrap gap-4 small">
        <div>{{ __('Balance on :date', ['date' => fmt_date($filters['from'])]) }}: <span class="fw-semibold">{{ fmt_qty($ledger['opening']) }} L</span></div>
        <div>{{ __('In') }}: <span class="fw-semibold text-success">{{ fmt_qty($ledger['in']) }} L</span></div>
        <div>{{ __('Out') }}: <span class="fw-semibold text-danger">{{ fmt_qty($ledger['out']) }} L</span></div>
        <div>{{ __('Balance on :date', ['date' => fmt_date($filters['to'])]) }} ({{ __('closing') }}): <span class="fw-semibold">{{ fmt_qty($ledger['closing']) }} L</span></div>
        @if ($filters['movement_type'])
            <div class="text-body-secondary">{{ __('In / Out show only the chosen movement; the balance column counts every movement.') }}</div>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Movement') }}</th>
                    <th>{{ __('Stock') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('Details') }}</th>
                    <th class="text-end">{{ __('In') }}</th>
                    <th class="text-end">{{ __('Out') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                    <th class="d-none d-lg-table-cell">{{ __('Entered by') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $movement)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($movement->movement_date) }}
                            @if ($movement->shift)<div class="small text-body-secondary">{{ $movement->shift->label() }}</div>@endif
                        </td>
                        <td>{{ $movement->movement_type->label() }}</td>
                        <td class="text-nowrap small">{{ $movement->stock_source->label() }} · {{ $movement->milk_type->label() }}</td>
                        <td class="d-none d-md-table-cell small">
                            @if ($movement->reference instanceof \App\Models\MilkProductionRecord)
                                {{ $movement->reference->animal?->label() }}
                            @else
                                {{ $movement->reason }}
                            @endif
                        </td>
                        <td class="text-end text-success">{{ \App\Support\Money::isZero($movement->quantity_in) ? '' : fmt_qty($movement->quantity_in) }}</td>
                        <td class="text-end text-danger">{{ \App\Support\Money::isZero($movement->quantity_out) ? '' : fmt_qty($movement->quantity_out) }}</td>
                        <td class="text-end fw-semibold {{ \App\Support\Money::isNegative($movement->balance) ? 'text-danger' : '' }}">{{ fmt_qty($movement->balance) }}</td>
                        <td class="d-none d-lg-table-cell small">{{ $movement->creator?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-5">{{ __('No milk movements for these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
