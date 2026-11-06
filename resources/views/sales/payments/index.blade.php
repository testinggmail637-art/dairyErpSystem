@extends('layouts.app')

@section('title', __('Customer payments'))

@section('page-actions')
    @can('customers.view')
        <a href="{{ route('customer-payments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Record payment') }}</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('customer-payments.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#payments-results">
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="{{ __('Customer name, code, phone or reference') }}">
            </div>
            <div class="col-6 col-md-2"><x-form.date name="from" :label="__('From')" :value="$filters['from']" group-class="mb-0" /></div>
            <div class="col-6 col-md-2"><x-form.date name="to" :label="__('To')" :value="$filters['to']" group-class="mb-0" /></div>
            <div class="col-6 col-md-2">
                <label for="payment_mode_id" class="form-label small mb-1">{{ __('Mode') }}</label>
                <select id="payment_mode_id" name="payment_mode_id" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($modes as $id => $name)
                        <option value="{{ $id }}" @selected($filters['payment_mode_id'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('customer-payments.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="payments-results" data-ajax-results>
    @fragment('results')
    <div class="card-body border-bottom small">{{ __('Total of these payments') }}: <strong><x-money :value="$total" /></strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Mode') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th class="text-end">{{ __('On bills') }}</th>
                    <th class="text-end">{{ __('Advance') }}</th>
                    <th>{{ __('Collected by') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    @php($advance = \App\Support\Money::sub($p->amount, $p->allocated ?? 0))
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($p->payment_date) }}</td>
                        <td>{{ $p->customer->name }} <span class="small font-monospace text-body-secondary">{{ $p->customer->code }}</span></td>
                        <td>{{ $p->paymentMode->name }}@if ($p->reference_no)<div class="small text-body-secondary">{{ $p->reference_no }}</div>@endif</td>
                        <td class="text-end fw-semibold"><x-money :value="$p->amount" /></td>
                        <td class="text-end"><x-money :value="$p->allocated ?? 0" /></td>
                        <td class="text-end">{{ \App\Support\Money::isZero($advance) ? '—' : inr($advance) }}</td>
                        <td class="small">{{ $p->collector?->name }}</td>
                        <td class="text-end">
                            @can('update', $p)
                                <a href="{{ route('customer-payments.edit', $p) }}" class="btn btn-outline-primary btn-sm" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-5">
                        <i class="bi bi-cash-coin fs-2 d-block mb-2"></i>{{ array_filter($filters) ? __('No payments match these filters.') : __('No payments yet.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($payments->hasPages())<div class="card-footer">{{ $payments->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Payments are set against finalized bills, oldest first. What is left is an advance and goes to the next bill when it is finalized.') }}</p>
@endsection
