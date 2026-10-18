@extends('layouts.app')

@section('title', $payment->exists ? __('Edit payment') : __('Record payment'))

@section('page-actions')
    @can('vendors.view')
        <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to ledger') }}</a>
    @endcan
    @if ($payment->exists)
        @can('delete', $payment)
            <x-confirm-form :action="route('vendor-payments.destroy', $payment)" :label="__('Delete')" reason
                            :message="__('Delete this payment of :amount?', ['amount' => inr($payment->amount)])" />
        @endcan
    @endif
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="alert alert-light border d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">{{ $vendor->name }}</div>
                @if ($vendor->phone)<div class="small text-body-secondary">{{ $vendor->phone }}</div>@endif
            </div>
            <div class="text-end">
                <div class="small text-body-secondary">{{ __('We owe now') }}</div>
                <div class="fs-5 fw-semibold"><x-money :value="$vendor->balance()" /></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $payment->exists ? route('vendor-payments.update', $payment) : route('vendor-payments.store') }}">
                    @csrf
                    @if ($payment->exists)
                        @method('PUT')
                    @else
                        {{-- Idempotency (spec 4.7): the same form submitted twice records one payment. --}}
                        <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
                    @endif
                    <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">

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
                            <x-form.textarea name="note" :label="__('Note')" :value="$payment->note" rows="2" maxlength="1000" group-class="mb-0" />
                        </div>
                    </div>

                    @if ($payment->exists)
                        <div class="mt-3"><x-form.reason :placeholder="__('e.g. Wrong amount entered')" /></div>
                    @endif

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save payment') }}</button>
                        @can('vendors.view')
                            <a href="{{ route('vendors.show', $vendor) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
