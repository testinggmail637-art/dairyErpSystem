@extends('layouts.app')

@section('title', $vendor->exists ? __('Edit vendor') : __('Add vendor'))

@section('page-actions')
    <a href="{{ route('vendors.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to vendors') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $vendor->exists ? route('vendors.update', $vendor) : route('vendors.store') }}">
                    @csrf
                    @if ($vendor->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Vendor name')" :value="$vendor->name" maxlength="150" required autofocus />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$vendor->phone" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="gstin" :label="__('GSTIN')" :value="$vendor->gstin" maxlength="15" class="text-uppercase" />
                        </div>
                    </div>

                    <x-form.textarea name="address" :label="__('Address')" :value="$vendor->address" rows="2" maxlength="500" />

                    <x-form.money name="opening_balance" :label="__('Opening balance (we owe)')" :value="$vendor->opening_balance"
                                  allow-negative :help="__('What we owed this vendor when we started using the software. Use a minus amount for an advance we had paid.')" />

                    <x-form.textarea name="notes" :label="__('Notes')" :value="$vendor->notes" rows="2" maxlength="1000" />

                    <x-form.switch name="is_active" :label="__('Active (can be chosen for new expenses)')" :checked="$vendor->is_active" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('vendors.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
