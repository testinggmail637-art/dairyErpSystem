{{-- Placeholder; dashboard cards and charts are built in Phase 7 (spec Section 7.5). --}}
@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
<div class="card">
    <div class="card-body py-5 text-center">
        <i class="bi bi-speedometer2 fs-1 text-primary d-block mb-2"></i>
        <h2 class="h5">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
        <p class="text-body-secondary mb-0">{{ __('Dashboard cards and charts will appear here as modules are built.') }}</p>
    </div>
</div>
@endsection
