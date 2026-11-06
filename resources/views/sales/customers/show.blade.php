@extends('layouts.app')

@section('title', $customer->name.' · '.$customer->code)

@section('page-actions')
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Customers') }}</a>
    <a href="{{ route('customers.ledger', $customer) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i>{{ __('Ledger') }}</a>
    @can('subscriptions.manage')
        <a href="{{ route('subscriptions.index', ['customer_id' => $customer->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-check me-1"></i>{{ __('Subscriptions') }}</a>
    @endcan
    @if ($canManage)
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('Details') }}</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">{{ __('Code') }}</dt><dd class="col-7 font-monospace">{{ $customer->code }}</dd>
                    <dt class="col-5">{{ __('Type') }}</dt>
                    <dd class="col-7">{{ $customer->customer_type->label() }}@if ($customer->b2b_category) · {{ $customer->b2b_category->label() }}@endif</dd>
                    <dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7">{{ collect([$customer->phone, $customer->alt_phone])->filter()->implode(', ') ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Address') }}</dt><dd class="col-7">{{ collect([$customer->address, $customer->area])->filter()->implode(', ') ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Route') }}</dt>
                    <dd class="col-7">{{ $customer->route?->name ?? '—' }}@if ($customer->route && $customer->route_sequence) · {{ __('stop :n', ['n' => $customer->route_sequence]) }}@endif</dd>
                    <dt class="col-5">{{ __('GSTIN') }}</dt><dd class="col-7 font-monospace">{{ $customer->gstin ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Opening balance') }}</dt><dd class="col-7"><x-money :value="$customer->opening_balance" /></dd>
                    <dt class="col-5">{{ __('Credit limit') }}</dt><dd class="col-7">@if ($customer->credit_limit !== null)<x-money :value="$customer->credit_limit" />@else — @endif</dd>
                    <dt class="col-5">{{ __('Status') }}</dt>
                    <dd class="col-7">
                        @if ($customer->is_active)<span class="badge text-bg-success">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endif
                    </dd>
                    @if ($customer->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $customer->notes }}</dd>@endif
                </dl>
            </div>
            @if ($canManage)
                <div class="card-footer text-end">
                    <x-confirm-form :action="route('customers.destroy', $customer)" reason :label="__('Delete customer')"
                                    :message="__('Delete the customer :name?', ['name' => $customer->name])" />
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card mb-3">
            <div class="card-header fw-semibold">{{ __('Rate today (per litre)') }}</div>
            <ul class="list-group list-group-flush">
                @forelse ($currentRates as $row)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $row['product']->name }}</span>
                        <span>
                            <x-money :value="$row['rate']" />
                            @if ($row['rate'] === $row['product']->default_rate)<span class="small text-body-secondary">({{ __('default') }})</span>@endif
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No active products.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">{{ __('Own rates') }}</div>
            @if ($canManage)
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('customers.rates.store', $customer) }}" class="row g-2 align-items-start">
                        @csrf
                        <div class="col-12 col-md-4">
                            <x-form.select name="product_id" :label="__('Product')" :options="$productOptions" :placeholder="__('Choose')" required groupClass="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.money name="rate" :label="__('Rate')" required groupClass="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.date name="effective_from" :label="__('From date')" :value="now()->toDateString()" required groupClass="mb-0" />
                        </div>
                        <div class="col-12 col-md-2 pt-md-4">
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-md-2">{{ __('Add rate') }}</button>
                        </div>
                    </form>
                    <div class="form-text">{{ __('A new rate applies to deliveries from its date. Bills and deliveries already made keep their old rate.') }}</div>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('From date') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th class="text-end">{{ __('Rate') }}</th>
                            @if ($canManage)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rates as $rate)
                            <tr>
                                <td>{{ fmt_date($rate->effective_from) }}</td>
                                <td>{{ $rate->product->name }}</td>
                                <td class="text-end"><x-money :value="$rate->rate" /></td>
                                @if ($canManage)
                                    <td class="text-end">
                                        <x-confirm-form :action="route('customers.rates.destroy', [$customer, $rate])" reason
                                                        :message="__('Remove this rate?')" />
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canManage ? 4 : 3 }}" class="text-center text-body-secondary py-4">{{ __('No own rates — the product default rate is used.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rates->hasPages())<div class="card-footer">{{ $rates->links() }}</div>@endif
        </div>

        <div class="card mt-3" id="adjustments">
            <div class="card-header fw-semibold">{{ __('Adjustments') }}</div>
            @can('create', \App\Models\CustomerAdjustment::class)
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('customers.adjustments.store', $customer) }}" class="row g-2 align-items-start">
                        @csrf
                        <div class="col-6 col-md-3">
                            <x-form.date name="adjustment_date" :label="__('Date')" :value="now()->toDateString()" max="today" required group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.money name="amount" :label="__('Amount')" allow-negative required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="reason" :label="__('Reason')" maxlength="500" required :placeholder="__('e.g. Diwali discount')" group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-2 pt-md-4">
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-md-2">{{ __('Add') }}</button>
                        </div>
                    </form>
                    <div class="form-text">{{ __('Plus = customer owes more (extra charge); minus = discount or correction. Shown on the next bill and in the balance.') }}</div>
                </div>
            @endcan
            <ul class="list-group list-group-flush small">
                @forelse ($adjustments as $adj)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span>
                            {{ fmt_date($adj->adjustment_date) }} · {{ $adj->reason }}
                            <span class="text-body-secondary d-block">{{ $adj->creator?->name }}</span>
                        </span>
                        <span class="text-nowrap">
                            <span class="{{ \App\Support\Money::isNegative($adj->amount) ? 'text-success' : 'text-danger' }}"><x-money :value="$adj->amount" /></span>
                            @can('delete', $adj)
                                <x-confirm-form :action="route('customers.adjustments.destroy', [$customer, $adj])" reason :message="__('Remove this adjustment?')" />
                            @endcan
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No adjustments.') }}</li>
                @endforelse
            </ul>
            @if ($adjustments->hasPages())<div class="card-footer">{{ $adjustments->fragment('adjustments')->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
