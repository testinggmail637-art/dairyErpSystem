@extends('layouts.app')

@section('title', $subscription->exists ? __('Edit subscription') : __('Add subscription'))

@section('page-actions')
    <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Subscriptions') }}</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $subscription->exists ? route('subscriptions.update', $subscription) : route('subscriptions.store') }}">
                    @csrf
                    @if ($subscription->exists) @method('PUT') @endif

                    <x-form.select name="customer_id" :label="__('Customer')" :options="$selectedCustomer" :selected="$subscription->customer_id"
                                   :placeholder="__('Search by name, code or phone')" select2 :data-ajax-url="route('customers.search')" required />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.select name="product_id" :label="__('Product')" :options="$products" :selected="$subscription->product_id"
                                           :placeholder="__('Choose')" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="$subscription->shift?->value" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.quantity name="quantity" :label="__('Litres / day')" :value="$subscription->quantity" required />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.select name="stock_source" :label="__('Milk from')" :options="\App\Enums\MilkSource::options()"
                                           :selected="$subscription->stock_source?->value" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.date name="start_date" :label="__('Start date')" :value="$subscription->start_date?->toDateString()" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.date name="end_date" :label="__('End date')" :value="$subscription->end_date?->toDateString()" :help="__('Empty = ongoing')" />
                        </div>
                    </div>

                    @if ($subscription->exists)
                        <x-form.select name="status" :label="__('Status')" :options="\App\Enums\SubscriptionStatus::options()" :selected="$subscription->status->value" required
                                       :help="__('Stopped = no more deliveries. For a few days away, add a pause instead.')" />
                    @endif

                    <x-form.textarea name="notes" :label="__('Notes')" :value="$subscription->notes" rows="2" maxlength="500" />

                    <p class="form-text">{{ __('Changes apply to delivery sheets made after saving; sheets already made keep their quantity.') }}</p>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
            @if ($subscription->exists)
                <div class="card-footer text-end">
                    <x-confirm-form :action="route('subscriptions.destroy', $subscription)" :label="__('Delete subscription')"
                                    :message="__('Delete this subscription and its pauses?')" />
                </div>
            @endif
        </div>
    </div>

    @if ($subscription->exists)
        <div class="col-12 col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Pauses (no delivery on these days)') }}</div>
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('subscriptions.pauses.store', $subscription) }}" class="row g-2">
                        @csrf
                        <div class="col-6">
                            <x-form.date name="from_date" :label="__('First day')" required group-class="mb-0" />
                        </div>
                        <div class="col-6">
                            <x-form.date name="to_date" :label="__('Last day')" required group-class="mb-0" />
                        </div>
                        <div class="col-12">
                            <x-form.input name="reason" :label="__('Reason')" maxlength="255" :placeholder="__('e.g. Out of town')" group-class="mb-0" />
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-pause-circle me-1"></i>{{ __('Add pause') }}</button>
                        </div>
                    </form>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($pauses as $pause)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                {{ fmt_date($pause->from_date) }} – {{ fmt_date($pause->to_date) }}
                                @if ($pause->reason)<span class="small text-body-secondary d-block">{{ $pause->reason }}</span>@endif
                            </span>
                            <x-confirm-form :action="route('subscriptions.pauses.destroy', [$subscription, $pause])" :message="__('Remove this pause?')" />
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No pauses.') }}</li>
                    @endforelse
                </ul>
                @if ($pauses->hasPages())<div class="card-footer">{{ $pauses->links() }}</div>@endif
            </div>
        </div>
    @endif
</div>
@endsection
