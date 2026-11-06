@extends('layouts.app')

@section('title', __('Ledger · :name', ['name' => $customer->name]))

@php($exportQuery = array_filter($filters) ?: ['all' => 1])

@section('page-actions')
    <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Customer') }}</a>
    <a href="{{ route('customers.ledger', [$customer, ...$exportQuery, 'format' => 'excel']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel') }}</a>
    <a href="{{ route('customers.ledger', [$customer, ...$exportQuery, 'format' => 'pdf']) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}</a>
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ __('Opening') }}</div><div class="fs-5"><x-money :value="$ledger['opening']" /></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ __('Charged') }}</div><div class="fs-5"><x-money :value="$ledger['debit']" /></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ __('Paid / less') }}</div><div class="fs-5"><x-money :value="$ledger['credit']" /></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100 border-primary"><div class="card-body">
        <div class="small text-body-secondary">{{ \App\Support\Money::isNegative($ledger['closing']) ? __('Advance with us') : __('Customer owes') }}</div>
        <div class="fs-4 fw-semibold"><x-money :value="$ledger['closing']" /></div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('customers.ledger', $customer) }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3"><x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" /></div>
            <div class="col-6 col-md-3"><x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" /></div>
            <div class="col-12 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>{{ __('Show period') }}</button>
                <a href="{{ route('customers.ledger', [$customer, 'all' => 1]) }}" class="btn btn-link btn-sm">{{ __('All time') }}</a>
            </div>
        </form>
        @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Entry') }}</th>
                    <th>{{ __('Details') }}</th>
                    <th class="text-end">{{ __('Charged (+)') }}</th>
                    <th class="text-end">{{ __('Paid / less (−)') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @if ($ledger['rows']->onFirstPage())
                    <tr class="table-light">
                        <td>{{ $filters['from'] ? fmt_date($filters['from']) : '—' }}</td>
                        <td colspan="4" class="fw-semibold">{{ __('Opening balance') }}</td>
                        <td class="text-end fw-semibold"><x-money :value="$ledger['opening']" /></td>
                    </tr>
                @endif
                @forelse ($ledger['rows'] as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row->entry_date) }}</td>
                        <td>{{ $row->type === 'bill' ? __('Bill') : ($row->type === 'adjustment' ? __('Adjustment') : __('Payment')) }}</td>
                        <td class="small">
                            @if ($row->type === 'bill')
                                <a href="{{ route('bills.show', $row->id) }}">{{ $row->detail }}</a>
                            @else
                                {{ $row->detail }}
                            @endif
                        </td>
                        <td class="text-end">{{ \App\Support\Money::isZero($row->debit) ? '' : inr($row->debit) }}</td>
                        <td class="text-end">{{ \App\Support\Money::isZero($row->credit) ? '' : inr($row->credit) }}</td>
                        <td class="text-end fw-semibold"><x-money :value="$row->balance" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No bills, payments or adjustments in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-group-divider">
                <tr class="fw-semibold">
                    <td colspan="3">{{ __('Period total') }}</td>
                    <td class="text-end"><x-money :value="$ledger['debit']" /></td>
                    <td class="text-end"><x-money :value="$ledger['credit']" /></td>
                    <td class="text-end"><x-money :value="$ledger['closing']" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if ($ledger['rows']->hasPages())<div class="card-footer">{{ $ledger['rows']->links() }}</div>@endif
</div>
<p class="form-text mt-2">
    {{ __('Delivered but not yet billed: :amount — shown apart, not in the balance until the bill is finalized.', ['amount' => inr($ledger['unbilled'])]) }}
</p>
@endsection
