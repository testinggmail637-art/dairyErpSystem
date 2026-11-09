@extends('layouts.app')

@section('title', $chart->exists ? __('Edit rate chart') : __('Add rate chart'))

@php
    $rows = old('slabs', $slabs->map(fn ($s) => $s->only(['fat_from', 'fat_to', 'snf_from', 'snf_to', 'rate_per_litre']))->all());
    $rows = $rows ?: [[]];
@endphp

@section('page-actions')
    <a href="{{ $chart->exists ? route('rate-charts.show', $chart) : route('rate-charts.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-xl-9">
        <div class="card">
            <div class="card-body">
                @if ($chart->exists)
                    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>{{ __('Milk already collected keeps the rate it was saved with. For a new price from a date, it is clearer to set "valid to" here and add a new chart (copy).') }}</div>
                @endif
                <form method="POST" action="{{ $chart->exists ? route('rate-charts.update', $chart) : route('rate-charts.store') }}" id="rate-chart-form">
                    @csrf
                    @if ($chart->exists) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-5">
                            <x-form.input name="name" :label="__('Name')" :value="$chart->name" maxlength="100" required autofocus :placeholder="__('e.g. Cow milk — April 2026')" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.select name="milk_type" :label="__('Milk')" :options="\App\Enums\MilkType::options()" :selected="$chart->milk_type?->value" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="method" :label="__('How the rate is worked out')" :options="\App\Enums\RateMethod::options()" :selected="$chart->method?->value" required />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-3" data-method="fixed">
                            <x-form.money name="fixed_rate" :label="__('Rate per litre')" :value="$chart->fixed_rate" />
                        </div>
                        <div class="col-6 col-md-3" data-method="fat_rate fat_snf_rate">
                            <x-form.money name="fat_rate" :label="__('₹ per fat point')" :value="$chart->fat_rate" :help="__('Rate = fat × this')" />
                        </div>
                        <div class="col-6 col-md-3" data-method="fat_snf_rate">
                            <x-form.money name="snf_rate" :label="__('₹ per SNF point')" :value="$chart->snf_rate" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <x-form.input name="min_fat" :label="__('Minimum fat %')" :value="$chart->min_fat" inputmode="decimal" :help="__('Below this: reject or accept at the calculated rate')" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="min_snf" :label="__('Minimum SNF %')" :value="$chart->min_snf" inputmode="decimal" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.date name="effective_from" :label="__('Valid from')" :value="$chart->effective_from" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.date name="effective_to" :label="__('Valid to')" :value="$chart->effective_to" :help="__('Empty = no end')" />
                        </div>
                    </div>

                    <div class="mb-3" data-method="slab">
                        <label class="form-label">{{ __('Slabs') }}</label>
                        @error('slabs')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1" id="slab-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Fat from') }}</th><th>{{ __('Fat to') }}</th><th>{{ __('SNF from') }}</th><th>{{ __('SNF to') }}</th>
                                        <th>{{ __('Rate / L') }}</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $i => $row)
                                        <tr data-slab-row>
                                            @foreach (['fat_from', 'fat_to', 'snf_from', 'snf_to', 'rate_per_litre'] as $field)
                                                <td>
                                                    <input type="text" inputmode="decimal" name="slabs[{{ $i }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}"
                                                           class="form-control form-control-sm @error('slabs.'.$i.'.'.$field) is-invalid @enderror"
                                                           aria-label="{{ $field }}">
                                                    @error('slabs.'.$i.'.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </td>
                                            @endforeach
                                            <td><button type="button" class="btn btn-link btn-sm text-danger" data-remove-slab title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-add-slab><i class="bi bi-plus-lg me-1"></i>{{ __('Add row') }}</button>
                        <div class="form-text">{{ __('Fat 3.0 – 3.4 → ₹38, 3.5 – 3.9 → ₹40 … Leave SNF empty when the rate depends on fat only. The first matching row is used.') }}</div>
                    </div>

                    <x-form.switch name="is_default" :label="__('Default chart for this milk (used for farmers without their own chart)')" :checked="$chart->is_default" />
                    <x-form.switch name="is_active" :label="__('Active')" :checked="$chart->is_active" />

                    @if ($chart->exists)
                        <x-form.reason :placeholder="__('e.g. Dairy plant raised the fat rate')" />
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ $chart->exists ? route('rate-charts.show', $chart) : route('rate-charts.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/rate-chart.js') }}"></script>
@endpush
