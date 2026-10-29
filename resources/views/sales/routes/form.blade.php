@extends('layouts.app')

@section('title', $route->exists ? __('Edit route') : __('Add route'))

@section('page-actions')
    <a href="{{ route('delivery-routes.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to routes') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $route->exists ? route('delivery-routes.update', $route) : route('delivery-routes.store') }}">
                    @csrf
                    @if ($route->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Route name')" :value="$route->name" maxlength="60" required
                                  :help="__('E.g. Station Road, Old City. Must be unique.')" />

                    <x-form.select name="delivery_user_id" :label="__('Delivery person')" :options="$deliveryUsers"
                                   :selected="$route->delivery_user_id" :placeholder="__('Not assigned')"
                                   :help="__('Users who can update deliveries. The delivery person sees only the customers of his routes.')" />

                    <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$route->sort_order" min="0" max="9999"
                                  :help="__('Routes are listed in this order on delivery sheets and dropdowns.')" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$route->is_active"
                                   :help="__('Inactive routes are not offered for customers; existing customers keep it.')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('delivery-routes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
