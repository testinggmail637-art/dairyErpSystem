@extends('layouts.app')

@section('title', $customer->exists ? __('Edit customer :code', ['code' => $customer->code]) : __('Add customer'))

@section('page-actions')
    <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
                    @csrf
                    @if ($customer->exists) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="name" :label="__('Customer name')" :value="$customer->name" maxlength="150" required autofocus />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.select name="customer_type" :label="__('Type')" :options="$types" :selected="$customer->customer_type?->value" required />
                        </div>
                        <div class="col-6 col-md-3" id="b2b-category-group">
                            <x-form.select name="b2b_category" :label="__('Business kind')" :options="$categories"
                                           :selected="$customer->b2b_category?->value" :placeholder="__('Choose')" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$customer->phone" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="alt_phone" type="tel" :label="__('Other number')" :value="$customer->alt_phone" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="gstin" :label="__('GSTIN')" :value="$customer->gstin" maxlength="15" class="text-uppercase" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <x-form.input name="address" :label="__('Address')" :value="$customer->address" maxlength="500" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="area" :label="__('Area / locality')" :value="$customer->area" maxlength="100" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-8 col-md-6">
                            <x-form.select name="route_id" :label="__('Delivery route')" :options="$routes" :selected="$customer->route_id"
                                           :placeholder="__('No route')" />
                        </div>
                        <div class="col-4 col-md-3">
                            <x-form.input name="route_sequence" type="number" :label="__('Stop no.')" :value="$customer->route_sequence" min="1" max="99999"
                                          :help="__('Order on the route')" />
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.money name="opening_balance" :label="__('Opening balance (customer owes)')" :value="$customer->opening_balance"
                                          allow-negative :help="__('What the customer owed when we started using the software. Use a minus amount for an advance.')" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.money name="credit_limit" :label="__('Credit limit')" :value="$customer->credit_limit" :help="__('Optional')" />
                        </div>
                    </div>

                    <x-form.textarea name="notes" :label="__('Notes')" :value="$customer->notes" rows="2" maxlength="1000" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$customer->is_active" />

                    @if ($customer->exists)
                        <x-form.reason :placeholder="__('e.g. New phone number')" />
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ $customer->exists ? route('customers.show', $customer) : route('customers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        const $type = $('#customer_type');
        const toggle = () => $('#b2b-category-group').toggleClass('d-none', $type.val() !== 'b2b');
        $type.on('change', toggle);
        toggle();
    });
</script>
@endpush
