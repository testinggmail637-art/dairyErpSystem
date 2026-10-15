{{-- Temporary page; replaced by the real layout (Task 0.2) and dashboard (Phase 7). --}}
@extends('layouts.guest')

@section('title', __('Dashboard'))

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">{{ __('Dashboard') }}</h1>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Logout') }}</button>
        </form>
    </div>
    <p class="mt-3">{{ __('Logged in as :name.', ['name' => auth()->user()->name]) }}</p>
</div>
@endsection
