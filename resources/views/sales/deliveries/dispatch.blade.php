@extends('layouts.app')

@section('title', __('One-off / B2B dispatch'))

@section('page-actions')
    <a href="{{ route('deliveries.grid') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Grid') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-9 col-xl-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('deliveries.dispatch.store') }}">
                    @csrf
                    <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>

                    <x-form.select name="customer_id" :label="__('Customer')" :options="$selectedCustomer"
                                   :placeholder="__('Search by name, code or phone')" select2 :data-ajax-url="route('customers.search')" required />

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.select name="product_id" :label="__('Product')" :options="$products" :placeholder="__('Choose')" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.date name="delivery_date" :label="__('Date')" :value="now()->toDateString()" max="today" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="shift" :label="__('Shift')" :options="\App\Enums\MilkShift::options()" :selected="\App\Enums\MilkShift::current()->value" required />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <x-form.quantity name="delivered_qty" :label="__('Litres sent')" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.money name="rate" :label="__('Rate / L')" :help="__('Empty = customer\'s rate')" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.select name="stock_source" :label="__('Milk from')" :options="\App\Enums\MilkSource::options()"
                                           :selected="\App\Enums\MilkSource::OwnFarm->value" required />
                        </div>
                    </div>

                    @include('sales.deliveries.partials.readings', ['delivery' => null])

                    <x-form.textarea name="notes" :label="__('Notes')" rows="2" maxlength="500" :placeholder="__('e.g. Tanker no., challan no.')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-truck me-1"></i>{{ __('Save dispatch') }}</button>
                        <a href="{{ route('deliveries.grid') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
