@extends('layouts.app')

@section('title', $chart->name)

@section('page-actions')
    <a href="{{ route('rate-charts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Rate Charts') }}</a>
    <a href="{{ route('rate-charts.create', ['copy' => $chart->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-files me-1"></i>{{ __('Copy as new chart') }}</a>
    <a href="{{ route('rate-charts.edit', $chart) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
    <x-confirm-form :action="route('rate-charts.destroy', $chart)" reason :label="__('Delete')" :message="__('Delete rate chart :name?', ['name' => $chart->name])" />
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('Rate chart') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Milk') }}</dt><dd class="col-7">{{ $chart->milk_type->label() }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Method') }}</dt><dd class="col-7">{{ $chart->method->label() }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Rates') }}</dt><dd class="col-7">@include('collection.rate-charts.rates', ['chart' => $chart])</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Valid') }}</dt>
                    <dd class="col-7">{{ fmt_date($chart->effective_from) }} – {{ $chart->effective_to ? fmt_date($chart->effective_to) : __('open') }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Default') }}</dt><dd class="col-7">{{ $chart->is_default ? __('Yes') : __('No') }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Status') }}</dt><dd class="col-7">{{ $chart->is_active ? __('Active') : __('Inactive') }}</dd>
                </dl>
            </div>
        </div>

        @if ($chart->slabs->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Slabs') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Fat') }}</th><th>{{ __('SNF') }}</th><th class="text-end">{{ __('Rate / L') }}</th></tr></thead>
                        <tbody>
                            @foreach ($chart->slabs as $slab)
                                <tr>
                                    <td>{{ $slab->fat_from }} – {{ $slab->fat_to }}</td>
                                    <td>{{ $slab->snf_from !== null ? $slab->snf_from.' – '.$slab->snf_to : __('any') }}</td>
                                    <td class="text-end">{{ inr($slab->rate_per_litre) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-lg-6">
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-calculator me-1"></i>{{ __('Rate preview') }}</div>
            <div class="card-body">
                <form id="rate-preview" data-url="{{ route('rate-charts.preview', $chart) }}" data-below="{{ __('Below the minimum fat / SNF: the operator rejects the milk or accepts it at this rate.') }}" class="row g-2 align-items-end">
                    <div class="col-4"><x-form.input name="qty" :label="__('Litres')" value="20" inputmode="decimal" group-class="mb-0" /></div>
                    <div class="col-4"><x-form.input name="fat" :label="__('Fat %')" inputmode="decimal" group-class="mb-0" /></div>
                    <div class="col-4"><x-form.input name="snf" :label="__('SNF %')" inputmode="decimal" group-class="mb-0" /></div>
                </form>
                <div class="row text-center mt-3" aria-live="polite">
                    <div class="col-6"><div class="small text-body-secondary">{{ __('Rate / L') }}</div><div class="fs-4 fw-semibold" data-preview="rate">—</div></div>
                    <div class="col-6"><div class="small text-body-secondary">{{ __('Amount') }}</div><div class="fs-4 fw-semibold" data-preview="amount">—</div></div>
                </div>
                <div class="text-center small mt-2" data-preview="message"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">{{ __('Farmers on this chart') }}</div>
            <div class="card-body">
                @forelse ($farmers as $farmer)
                    <a href="{{ route('farmers.show', $farmer) }}" class="badge text-bg-light text-decoration-none me-1 mb-1">{{ $farmer->code }} · {{ $farmer->name }}</a>
                @empty
                    <span class="text-body-secondary small">{{ $chart->is_default ? __('Used for every farmer of this milk without an own chart.') : __('No farmer has this as own chart.') }}</span>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/rate-chart.js') }}"></script>
@endpush
