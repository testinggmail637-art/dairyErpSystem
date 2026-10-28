@extends('layouts.app')

@section('title', __('Milk drop alerts'))

@section('page-actions')
    <a href="{{ route('milk-production.index', ['date' => $date]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-grid-3x3 me-1"></i>{{ __('Entry grid') }}</a>
@endsection

@section('content')
@php
    $prev = \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString();
    $next = \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString();
@endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <form method="GET" action="{{ route('milk-alerts.index') }}" class="d-flex flex-wrap gap-2 align-items-end" id="alerts-date-form">
            <a href="{{ route('milk-alerts.index', ['date' => $prev]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Previous day') }}"><i class="bi bi-chevron-left"></i></a>
            <div>
                <label for="date" class="form-label small mb-1">{{ __('Date') }}</label>
                <x-form.date name="date" :value="$date" max="today" group-class="mb-0" />
            </div>
            @if ($date < today()->toDateString())
                <a href="{{ route('milk-alerts.index', ['date' => $next]) }}" class="btn btn-outline-secondary" aria-label="{{ __('Next day') }}"><i class="bi bi-chevron-right"></i></a>
            @endif
        </form>
        <div class="ms-md-auto small text-body-secondary">
            {{ __('Alert when a shift\'s milk is :percent % or more below the average of the animal\'s previous :entries entries of that shift (at least :min needed, last :days days).', [
                'percent' => $threshold, 'entries' => \App\Services\MilkDropAlertService::HISTORY_ENTRIES,
                'min' => \App\Services\MilkDropAlertService::MIN_ENTRIES, 'days' => \App\Services\MilkDropAlertService::LOOKBACK_DAYS,
            ]) }}
            @can('settings.manage')
                <a href="{{ route('settings.farm.edit') }}">{{ __('Change %') }}</a>
            @endcan
        </div>
    </div>
</div>

@if ($alerts->isEmpty())
    <div class="card">
        <div class="card-body text-center text-body-secondary py-5">
            <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>{{ __('No milk drop alerts on :date.', ['date' => fmt_date($date)]) }}
        </div>
    </div>
@else
    @include('herd.milk._alerts', ['alerts' => $alerts])
@endif
@endsection

@push('scripts')
    <script>
        $('#alerts-date-form').on('change', 'input[name="date"]', function () { if (this.value) this.form.submit(); });
    </script>
@endpush
