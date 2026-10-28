@extends('layouts.app')

@section('title', __('Reports'))

@section('content')
@foreach ($groups as $group => $reports)
    <h2 class="h6 text-body-secondary text-uppercase mb-2">{{ $group }}</h2>
    <div class="row g-3 mb-4">
        @foreach ($reports as $report)
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ $report['url'] }}" class="card h-100 text-decoration-none text-body app-report-card">
                    <div class="card-body">
                        <i class="bi {{ $report['icon'] }} fs-3 text-primary"></i>
                        <div class="fw-semibold mt-2">{{ $report['title'] }}</div>
                        <div class="small text-body-secondary">{{ $report['description'] }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
<p class="form-text">{{ __('More reports (milk stock, sales, collection, profit) appear here as those modules are added.') }}</p>
@endsection
