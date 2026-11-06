@extends('layouts.app')

@section('title', $payment->exists ? __('Edit payment') : __('Record payment'))

@section('page-actions')
    <a href="{{ route('customer-payments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Payments') }}</a>
    @if ($payment->exists)
        @can('delete', $payment)
            <x-confirm-form :action="route('customer-payments.destroy', $payment)" :label="__('Delete')" reason
                            :message="__('Delete this payment of :amount?', ['amount' => inr($payment->amount)])" />
        @endcan
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $payment->exists ? route('customer-payments.update', $payment) : route('customer-payments.store') }}">
                    @csrf
                    @if ($payment->exists)
                        @method('PUT')
                        <div class="mb-3"><span class="text-body-secondary small">{{ __('Customer') }}</span><div class="fw-semibold">{{ $customer?->name }} <span class="small font-monospace">{{ $customer?->code }}</span></div></div>
                    @else
                        <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
                        <x-form.select name="customer_id" :label="__('Customer')" :options="$selectedCustomer" :selected="$payment->customer_id"
                                       :placeholder="__('Search by name, code or phone')" select2 :data-ajax-url="route('customers.search')" required />
                    @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.date name="payment_date" :label="__('Payment date')" :value="$payment->payment_date" max="today" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="amount" :label="__('Amount')" :value="$payment->amount" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="payment_mode_id" :label="__('Payment mode')" :options="$paymentModes" :selected="$payment->payment_mode_id"
                                           :placeholder="__('Choose')" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="reference_no" :label="__('Reference no.')" :value="$payment->reference_no" maxlength="50"
                                          :placeholder="__('UPI ref / cheque no.')" group-class="mb-0" />
                        </div>
                        <div class="col-12">
                            <x-form.textarea name="note" :label="__('Note')" :value="$payment->note" rows="2" maxlength="500" group-class="mb-0" />
                        </div>
                    </div>

                    @if ($payment->exists)
                        <div class="mt-3"><x-form.reason :placeholder="__('e.g. Wrong amount entered')" /></div>
                    @endif

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save payment') }}</button>
                        <a href="{{ route('customer-payments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if ($payment->exists)
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Set against bills') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($payment->allocations as $a)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('bills.show', $a->bill_id) }}">{{ $a->bill->bill_no }}</a>
                            <x-money :value="$a->amount" />
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('Not on any bill yet — kept as advance.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection
