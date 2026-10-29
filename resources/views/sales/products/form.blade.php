@extends('layouts.app')

@section('title', $product->exists ? __('Edit product') : __('Add product'))

@section('page-actions')
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to products') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}">
                    @csrf
                    @if ($product->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Product name')" :value="$product->name" maxlength="60" required
                                  :help="__('E.g. Cow Milk, Buffalo Milk. Only raw milk is sold in this version.')" />

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <x-form.input name="code" :label="__('Code')" :value="$product->code" maxlength="10" required
                                          :help="__('Short code, e.g. COW. Letters, digits and -.')" class="text-uppercase" />
                        </div>
                        <div class="col-sm-6">
                            <x-form.select name="milk_type" :label="__('Milk')" :options="\App\Enums\MilkType::options()"
                                           :selected="$product->milk_type?->value" required
                                           :help="__('Deliveries take milk out of this type\'s stock.')" />
                        </div>
                    </div>

                    <x-form.money name="default_rate" :label="__('Default rate per litre')" :value="$product->default_rate" required
                                  :help="__('For customers without their own rate. New rate applies to deliveries from now on; history keeps its rate.')" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$product->is_active"
                                   :help="__('Inactive products are not offered for new subscriptions or deliveries.')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
