@extends('layouts.app')

@section('title', __('Edit delivery'))

@section('page-actions')
    <a href="{{ route('deliveries.grid', ['date' => $delivery->delivery_date->toDateString()]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Grid') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-8 col-xl-6">
        <div class="card">
            <div class="card-header">
                <strong>{{ $delivery->customer->name }}</strong> <span class="small font-monospace text-body-secondary">{{ $delivery->customer->code }}</span>
                <div class="small text-body-secondary">
                    {{ fmt_date($delivery->delivery_date) }} · {{ $delivery->shift->label() }} · {{ $delivery->product->name }}
                    · {{ $delivery->route?->name ?? __('No route') }} · {{ __('planned :qty L', ['qty' => fmt_qty($delivery->planned_qty)]) }}
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-form.select name="status" :label="__('Status')" :options="\App\Enums\DeliveryStatus::options()" :selected="$delivery->status->value" required />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.quantity name="delivered_qty" :label="__('Delivered')" :value="$delivery->status === \App\Enums\DeliveryStatus::Delivered ? $delivery->delivered_qty : $delivery->planned_qty"
                                             :help="__('Used when Delivered')" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.money name="rate" :label="__('Rate / L')" :value="$delivery->rate" required />
                        </div>
                    </div>

                    <x-form.input name="skip_reason" :label="__('Skip reason')" :value="$delivery->skip_reason" maxlength="255" :help="__('Used when Skipped')" />
                    <x-form.textarea name="notes" :label="__('Notes')" :value="$delivery->notes" rows="2" maxlength="500" />
                    <x-form.reason :placeholder="__('e.g. Customer took 1 L extra')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('deliveries.grid', ['date' => $delivery->delivery_date->toDateString()]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
