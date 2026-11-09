@extends('layouts.app')

@section('title', __('Farmer payments'))

@section('content')
@can('farmer_payments.manage')
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('farmer-payments.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-6 col-md-3"><x-form.date name="from_date" :label="__('From')" :value="$from" max="today" group-class="mb-0" required /></div>
                <div class="col-6 col-md-3"><x-form.date name="to_date" :label="__('To')" :value="$to" max="today" group-class="mb-0" required /></div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1"></i>{{ __('Make payment cycle') }}</button>
                </div>
                <div class="col-12 form-text">{{ __('Makes a draft statement for every farmer with accepted milk in these dates and locks that milk. Delete the draft to unlock it.') }}</div>
            </form>
        </div>
    </div>
@endcan

<div class="card" id="cycles" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Cycle') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Farmers') }}</th>
                    <th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Net payable') }}</th><th class="text-end">{{ __('Paid') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cycles as $cycle)
                    <tr>
                        <td><a href="{{ route('farmer-payments.show', $cycle) }}" class="fw-semibold font-monospace">{{ $cycle->cycle_no }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($cycle->from_date) }} – {{ fmt_date($cycle->to_date) }}</td>
                        <td><span @class(['badge', 'text-bg-warning' => $cycle->isDraft(), 'text-bg-success' => ! $cycle->isDraft()])>{{ $cycle->status->label() }}</span></td>
                        <td class="text-end">{{ $cycle->payments_count }}</td>
                        <td class="text-end">{{ inr($cycle->gross ?? 0) }}</td>
                        <td class="text-end fw-semibold">{{ inr($cycle->net ?? 0) }}</td>
                        <td class="text-end">{{ inr($cycle->paid ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">{{ __('No payment cycles yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($cycles->hasPages())<div class="card-footer">{{ $cycles->links() }}</div>@endif
    @endfragment
</div>
@endsection
