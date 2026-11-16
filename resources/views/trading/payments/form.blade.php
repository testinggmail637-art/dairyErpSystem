@extends('layouts.app')

@section('title', __('Record party payment'))

@section('page-actions')
    <a href="{{ $party ? route('parties.ledger', $party) : route('animal-payments.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ $party ? __('Back to ledger') : __('Back to payments') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('animal-payments.store') }}" id="party-payment-form">
                    @csrf
                    <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>

                    @if ($party)
                        <input type="hidden" name="party_id" value="{{ $party->id }}">
                        <div class="mb-3">
                            <div class="form-label">{{ __('Party') }}</div>
                            <div class="fw-semibold">{{ $party->name }}@if ($party->village) · {{ $party->village }}@endif</div>
                            <a href="{{ route('animal-payments.create') }}" class="small">{{ __('Choose another party') }}</a>
                        </div>
                    @else
                        <x-form.select name="party_id" :label="__('Party')" :options="[]" :placeholder="__('Search party')"
                                       select2 :data-ajax-url="route('parties.search')" required
                                       :help="__('To link the payment to a purchase or sale, open it from that purchase / sale or the party ledger.')" />
                    @endif

                    <div class="mb-3">
                        <div class="form-label">{{ __('Direction') }} *</div>
                        @foreach (\App\Enums\PaymentDirection::options() as $value => $label)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="direction" id="direction_{{ $value }}" value="{{ $value }}" @checked($direction === $value) required>
                                <label class="form-check-label" for="direction_{{ $value }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        @error('direction')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @if ($purchases)
                        <div data-show-when="paid">
                            <x-form.select name="animal_purchase_id" :label="__('For purchase')" :options="$purchases" :selected="$purchaseId" :placeholder="__('Not for one purchase')" />
                        </div>
                    @endif
                    @if ($sales)
                        <div data-show-when="received">
                            <x-form.select name="animal_sale_id" :label="__('For sale')" :options="$sales" :selected="$saleId" :placeholder="__('Not for one sale')" />
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.date name="payment_date" :label="__('Payment date')" :value="today()->toDateString()" max="today" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="amount" :label="__('Amount')" required />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.select name="payment_mode_id" :label="__('Payment mode')" :options="$modes" :placeholder="__('Choose mode')" required />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="reference_no" :label="__('Reference no.')" maxlength="50" />
                        </div>
                    </div>

                    <x-form.textarea name="note" :label="__('Note')" rows="2" maxlength="500" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save payment') }}</button>
                        <a href="{{ $party ? route('parties.ledger', $party) : route('animal-payments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/party-payment-form.js') }}"></script>
@endpush
