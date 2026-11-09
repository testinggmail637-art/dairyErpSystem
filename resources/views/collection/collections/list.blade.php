@extends('layouts.app')

@section('title', __('Milk collections'))

@section('page-actions')
    <a href="{{ route('collections.index') }}" class="btn btn-primary btn-sm"><i class="bi bi-keyboard me-1"></i>{{ __('Collection entry') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('collections.list') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#collection-list">
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <x-form.select name="farmer_id" :label="__('Farmer')" :options="$selectedFarmer" :selected="$filters['farmer_id']"
                               :placeholder="__('All')" select2 :data-ajax-url="route('farmers.search')" group-class="mb-0" />
            </div>
            <div class="col-4 col-md-2 col-xl-1">
                <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="$filters['shift']"
                               :placeholder="__('Both')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-4 col-md-2 col-xl-1">
                <x-form.select name="milk_type" :label="__('Milk')" :options="\App\Enums\MilkType::options()" :selected="$filters['milk_type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-4 col-md-2 col-xl-1">
                <x-form.select name="status" :label="__('Status')" :options="\App\Enums\QualityStatus::options()" :selected="$filters['status']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-12 col-md-6 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('collections.list') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="collection-list" data-ajax-results>
    @fragment('results')
    {{-- totals of the whole filtered set (one SQL query) --}}
    <div class="card-body border-bottom d-flex flex-wrap gap-4 small">
        <div>{{ __('Entries') }}: <span class="fw-semibold">{{ $totals->cnt }}</span></div>
        <div>{{ __('Accepted') }}: <span class="fw-semibold">{{ fmt_qty($totals->litres) }} L</span></div>
        <div>{{ __('Rejected') }}: <span class="fw-semibold">{{ fmt_qty($totals->rejected) }} L</span></div>
        <div>{{ __('Amount') }}: <span class="fw-semibold"><x-money :value="$totals->amount" /></span></div>
    </div>
    @include('collection.collections._table', ['entries' => $entries, 'showDate' => true])
    @if ($entries->hasPages())<div class="card-footer">{{ $entries->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
