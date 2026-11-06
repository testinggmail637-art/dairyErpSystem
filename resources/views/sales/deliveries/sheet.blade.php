@extends('layouts.app')

@section('title', __('Delivery sheet'))

@section('page-actions')
    @can('deliveries.view_all_routes')
        <a href="{{ route('deliveries.index', ['date' => $date]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Summary') }}</a>
    @endcan
@endsection

@section('content')
<form method="GET" action="{{ route('deliveries.sheet') }}" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <x-form.date name="date" :label="__('Date')" :value="$date" group-class="mb-0" />
        </div>
        <div class="col-6 col-md-3">
            <label for="shift" class="form-label small mb-1">{{ __('Shift') }}</label>
            <select id="shift" name="shift" class="form-select">
                @foreach (\App\Enums\MilkShift::options() as $value => $label)
                    <option value="{{ $value }}" @selected($shift->value === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-8 col-md-4">
            <label for="route_id" class="form-label small mb-1">{{ __('Route') }}</label>
            <select id="route_id" name="route_id" class="form-select">
                <option value="">{{ $routes->count() > 1 || auth()->user()->can('deliveries.view_all_routes') ? __('All my routes') : __('My route') }}</option>
                @foreach ($routes as $id => $name)
                    <option value="{{ $id }}" @selected($routeId === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-4 col-md-2">
            <button type="submit" class="btn btn-primary w-100">{{ __('Show') }}</button>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap gap-3 small mb-3">
    <span>{{ __('Customers') }}: <strong>{{ $totals->total }}</strong></span>
    <span>{{ __('Pending') }}: <strong>{{ (int) $totals->pending }}</strong></span>
    <span>{{ __('Planned') }}: <strong>{{ fmt_qty($totals->planned ?? 0) }} L</strong></span>
    <span>{{ __('Delivered') }}: <strong>{{ fmt_qty($totals->delivered ?? 0) }} L</strong></span>
</div>

@forelse ($deliveries as $d)
    @php($locked = $d->isBilled() || $d->delivery_date->toDateString() > $today)
    <div class="card mb-2" id="delivery-{{ $d->id }}">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div class="fw-semibold">
                        @if ($d->customer->route_sequence)<span class="text-body-secondary">#{{ $d->customer->route_sequence }}</span>@endif
                        {{ $d->customer->name }}
                    </div>
                    <div class="small text-body-secondary">{{ collect([$d->customer->address, $d->customer->area])->filter()->implode(', ') }}</div>
                    @if ($d->customer->phone)
                        <a href="tel:{{ $d->customer->phone }}" class="small"><i class="bi bi-telephone"></i> {{ $d->customer->phone }}</a>
                    @endif
                </div>
                <div class="text-end text-nowrap">
                    <div>{{ $d->product->name }} · <strong>{{ fmt_qty($d->planned_qty) }} L</strong></div>
                    @switch($d->status)
                        @case(\App\Enums\DeliveryStatus::Delivered)
                            <span class="badge text-bg-success">{{ __('Delivered :qty L', ['qty' => fmt_qty($d->delivered_qty)]) }}</span>
                            @break
                        @case(\App\Enums\DeliveryStatus::Skipped)
                            <span class="badge text-bg-secondary" title="{{ $d->skip_reason }}">{{ __('Skipped') }}</span>
                            @break
                        @default
                            <span class="badge text-bg-warning">{{ __('Pending') }}</span>
                    @endswitch
                    @if (isset($collected[$d->customer_id]))
                        <div class="small text-success">{{ __('Paid today :amount', ['amount' => inr($collected[$d->customer_id])]) }}</div>
                    @endif
                </div>
            </div>

            @if ($d->isBilled())
                <div class="small text-body-secondary mt-2"><i class="bi bi-lock"></i> {{ __('Billed — locked') }}</div>
            @elseif (! $locked)
                <form method="POST" action="{{ route('deliveries.deliver', $d) }}" class="d-flex gap-2 mt-2">
                    @csrf
                    <div class="input-group input-group-sm" style="max-width: 9rem">
                        <input type="text" name="delivered_qty" inputmode="decimal" class="form-control" aria-label="{{ __('Litres') }}"
                               value="{{ $d->status === \App\Enums\DeliveryStatus::Delivered ? $d->delivered_qty : $d->planned_qty }}">
                        <span class="input-group-text">L</span>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2"></i> {{ $d->status === \App\Enums\DeliveryStatus::Delivered ? __('Update') : __('Delivered') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#skip-{{ $d->id }}">{{ __('Skip') }}</button>
                    @if ($paymentModes->isNotEmpty())
                        <button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-bs-toggle="collapse" data-bs-target="#pay-{{ $d->id }}"><i class="bi bi-cash"></i><span class="d-none d-sm-inline"> {{ __('Collect payment') }}</span></button>
                    @endif
                </form>

                <div class="collapse" id="skip-{{ $d->id }}">
                    <form method="POST" action="{{ route('deliveries.skip', $d) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        <input type="text" name="skip_reason" class="form-control form-control-sm" maxlength="255" required placeholder="{{ __('Reason, e.g. House locked') }}">
                        <button type="submit" class="btn btn-secondary btn-sm">{{ __('Skip') }}</button>
                    </form>
                </div>
            @endif

            @if ($paymentModes->isNotEmpty() && ! $d->isBilled())
                <div class="collapse" id="pay-{{ $d->id }}">
                    <form method="POST" action="{{ route('deliveries.collect', $d) }}" class="row g-2 mt-1">
                        @csrf
                        <input type="hidden" name="client_uuid" data-client-uuid>
                        <div class="col-6 col-md-3"><input type="text" name="amount" inputmode="decimal" class="form-control form-control-sm" required placeholder="{{ __('Amount ₹') }}"></div>
                        <div class="col-6 col-md-3">
                            <select name="payment_mode_id" class="form-select form-select-sm" required aria-label="{{ __('Mode') }}">
                                @foreach ($paymentModes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-8 col-md-4"><input type="text" name="reference_no" maxlength="50" class="form-control form-control-sm" placeholder="{{ __('UPI ref (optional)') }}"></div>
                        <div class="col-4 col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Save') }}</button></div>
                    </form>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="text-center text-body-secondary py-5">
        <i class="bi bi-truck fs-2 d-block mb-2"></i>{{ __('No deliveries for this date, shift and route.') }}
    </div>
@endforelse

@if ($deliveries->hasPages())<div class="mt-3">{{ $deliveries->links() }}</div>@endif
@endsection
