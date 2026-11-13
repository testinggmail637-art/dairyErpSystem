@extends('layouts.app')

@section('title', $tx->exists ? __('Edit feed entry') : __('New feed entry'))

@section('page-actions')
    <a href="{{ route('feed.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Feed stock') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <form method="POST" action="{{ $tx->exists ? route('feed.transactions.update', $tx) : route('feed.transactions.store') }}" autocomplete="off" id="feed-form">
            @csrf
            @if ($tx->exists) @method('PUT') @endif

            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <x-form.select name="type" :label="__('Entry')" :options="$types" :selected="old('type', $tx->type?->value)" required />
                </div>
                <div class="col-12 col-md-5">
                    <x-form.select name="feed_item_id" :label="__('Feed')" :options="$items" :selected="old('feed_item_id', $tx->feed_item_id)" :placeholder="__('Choose')" required />
                </div>
                <div class="col-12 col-md-3">
                    <x-form.date name="transaction_date" :label="__('Date')" :value="$tx->transaction_date" max="today" required />
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6 col-md-4"><x-form.quantity name="quantity" :label="__('Quantity')" :value="$tx->quantity" unit="" required /></div>
                <div class="col-6 col-md-4" data-feed-show="purchase">
                    <x-form.money name="amount" :label="__('Bill amount')" :value="$tx->type === \App\Enums\FeedTransactionType::Purchase ? $tx->amount : null"
                                  :help="__('Rate per unit is worked out from it.')" />
                </div>
                <div class="col-6 col-md-4" data-feed-show="opening adjustment_in">
                    <x-form.money name="rate" :label="__('Rate per unit (optional)')" :value="$tx->type === \App\Enums\FeedTransactionType::Purchase ? null : $tx->rate" />
                </div>
            </div>

            <fieldset class="border rounded p-3 mb-3" data-feed-show="purchase">
                <legend class="fs-6 w-auto px-1 mb-0">{{ __('Purchase — also saved as one expense (Feed, section Herd)') }}</legend>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <x-form.select name="vendor_id" :label="__('Vendor')" :options="$vendorOptions" :selected="old('vendor_id', $tx->vendor_id)"
                                       :placeholder="__('Search vendor')" select2 :data-ajax-url="route('vendors.search')" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-form.switch name="is_credit" :label="__('Credit (not paid yet — adds to vendor payable)')" :checked="old('is_credit', $tx->is_credit)" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :selected="old('payment_mode_id', $tx->payment_mode_id)"
                                       :placeholder="__('Choose')" :help="__('Paid by card? Enter it under Expenses.')" />
                    </div>
                    <div class="col-12 col-md-6">
                        <x-form.select name="expense_subcategory_id" :label="__('Expense subcategory')" :options="$subcategories"
                                       :selected="old('expense_subcategory_id', $subcategoryId)" :placeholder="__('Feed (no subcategory)')" />
                    </div>
                </div>
            </fieldset>

            <div data-feed-show="consumption">
                <x-form.select name="animal_id" :label="__('For one animal (optional)')" :options="$animalOptions" :selected="old('animal_id', $tx->animal_id)"
                               :placeholder="__('Whole herd')" select2 :data-ajax-url="route('animals.search', ['active' => 1])" />
            </div>

            <x-form.textarea name="notes" :label="__('Notes')" :value="$tx->notes" rows="2" maxlength="500" />
            @if ($tx->exists)
                <x-form.reason />
            @endif
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // show only the fields of the chosen entry type
    $(function () {
        const $type = $('#feed-form [name="type"]');
        const toggle = () => $('[data-feed-show]').each(function () {
            $(this).toggleClass('d-none', ! $(this).data('feed-show').split(' ').includes($type.val()));
        });
        $type.on('change', toggle);
        toggle();
    });
</script>
@endpush
