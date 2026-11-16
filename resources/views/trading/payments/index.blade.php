@extends('layouts.app')

@section('title', __('Party payments'))

@php($canManage = auth()->user()->can('animal_trading.manage'))
@php($filtered = $filters['party_id'] || $filters['direction'] !== '' || $filters['from'] !== '' || $filters['to'] !== '')

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('animal-payments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Record payment') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('animal-payments.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#payments-results">
            <div class="col-12 col-md-3">
                <x-form.select name="party_id" :label="__('Party')" :options="$selectedParty" :selected="$filters['party_id']" :placeholder="__('All parties')"
                               select2 :data-ajax-url="route('parties.search')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-2">
                <label for="direction" class="form-label small mb-1">{{ __('Direction') }}</label>
                <select id="direction" name="direction" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\PaymentDirection::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['direction'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('animal-payments.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="payments-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Party') }}</th>
                    <th>{{ __('Direction') }}</th>
                    <th>{{ __('For') }}</th>
                    <th>{{ __('Mode / reference') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($payment->payment_date) }}</td>
                        <td><a href="{{ route('parties.ledger', $payment->party_id) }}">{{ $payment->party->name }}</a></td>
                        <td>
                            <span class="badge {{ $payment->direction === \App\Enums\PaymentDirection::Paid ? 'text-bg-warning' : 'text-bg-success' }}">{{ $payment->direction->label() }}</span>
                        </td>
                        <td>
                            @if ($payment->purchase)
                                <a href="{{ route('animal-purchases.show', $payment->animal_purchase_id) }}">{{ $payment->purchase->purchase_no }}</a>
                            @elseif ($payment->sale)
                                <a href="{{ route('animal-sales.show', $payment->animal_sale_id) }}">{{ $payment->sale->sale_no }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $payment->paymentMode->name }}@if ($payment->reference_no) · {{ $payment->reference_no }}@endif</td>
                        <td class="text-end fw-semibold"><x-money :value="$payment->amount" /></td>
                        @if ($canManage)
                            <td class="text-end">
                                <x-confirm-form :action="route('animal-payments.destroy', $payment)" reason
                                                :message="__('Delete this payment of :amount?', ['amount' => inr($payment->amount)])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-cash-stack fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No payments match these filters.') : __('No party payments yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payments->hasPages())<div class="card-footer">{{ $payments->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
