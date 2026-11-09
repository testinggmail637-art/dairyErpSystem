@extends('layouts.app')

@section('title', __('Farmer advances'))

@section('content')
<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-cash-coin me-1"></i>{{ __('Give advance') }}</div>
            <div class="card-body">
                @include('collection.advances._form', ['farmerOptions' => [], 'farmerId' => null])
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('farmer-advances.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#advance-list">
                    <div class="col-6 col-md-3">
                        <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
                    </div>
                    <div class="col-6 col-md-3">
                        <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
                    </div>
                    <div class="col-12 col-md-4">
                        <x-form.select name="farmer_id" id="filter_farmer_id" :label="__('Farmer')" :options="$selectedFarmer" :selected="$filters['farmer_id']"
                                       :placeholder="__('All')" select2 :data-ajax-url="route('farmers.search')" group-class="mb-0" />
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                        <a href="{{ route('farmer-advances.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
                    </div>
                </form>
            </div>

            <div id="advance-list" data-ajax-results>
            @fragment('results')
            <div class="card-body border-bottom small d-flex gap-4">
                <div>{{ __('Advances') }}: <span class="fw-semibold">{{ $count }}</span></div>
                <div>{{ __('Total') }}: <span class="fw-semibold"><x-money :value="$total" /></span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th><th>{{ __('Farmer') }}</th><th class="text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Paid by') }}</th><th>{{ __('Note') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($advances as $advance)
                            <tr>
                                <td class="text-nowrap">{{ fmt_date($advance->advance_date) }}</td>
                                <td><a href="{{ route('farmers.show', $advance->farmer_id) }}"><span class="font-monospace small">{{ $advance->farmer->code }}</span> {{ $advance->farmer->name }}</a></td>
                                <td class="text-end fw-semibold">{{ inr($advance->amount) }}</td>
                                <td class="small">{{ $advance->paymentMode?->name }}@if ($advance->reference_no) · {{ $advance->reference_no }}@endif</td>
                                <td class="small">{{ $advance->note }}</td>
                                <td class="text-end">
                                    <x-confirm-form :action="route('farmer-advances.destroy', $advance)" reason
                                                    :message="__('Delete the advance of :amount to :code?', ['amount' => inr($advance->amount), 'code' => $advance->farmer->code])" />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No advances in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($advances->hasPages())<div class="card-footer">{{ $advances->links() }}</div>@endif
            @endfragment
            </div>
        </div>
    </div>
</div>
@endsection
