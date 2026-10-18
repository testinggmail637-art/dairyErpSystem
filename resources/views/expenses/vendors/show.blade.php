@extends('layouts.app')

@section('title', $vendor->name)

@section('page-actions')
    <a href="{{ route('vendors.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to vendors') }}</a>
    @can('vendors.manage')
        <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit vendor') }}</a>
    @endcan
    @can('create', \App\Models\VendorPayment::class)
        <a href="{{ route('vendor-payments.create', ['vendor_id' => $vendor->id]) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-cash-coin me-1"></i>{{ __('Record payment') }}
        </a>
    @endcan
@endsection

@section('content')
{{-- Summary: what we owe right now (all time) --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Opening balance') }}</div>
            <div class="fs-5"><x-money :value="$vendor->opening_balance" /></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Credit purchases') }}</div>
            <div class="fs-5"><x-money :value="$vendor->credit_total ?? 0" /></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Paid to vendor') }}</div>
            <div class="fs-5"><x-money :value="$vendor->paid_total ?? 0" /></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 border-primary"><div class="card-body">
            <div class="small text-body-secondary">{{ \App\Support\Money::isNegative($vendor->balance()) ? __('Advance with vendor') : __('We owe') }}</div>
            <div class="fs-4 fw-semibold"><x-money :value="$vendor->balance()" /></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('vendors.show', $vendor) }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-funnel me-1"></i>{{ __('Show period') }}</button>
                <a href="{{ route('vendors.show', [$vendor, 'all' => 1]) }}" class="btn btn-link btn-sm">{{ __('All time') }}</a>
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
                    <th class="text-end">{{ __('Purchase (+)') }}</th>
                    <th class="text-end">{{ __('Payment (−)') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @if ($ledger['rows']->onFirstPage())
                    <tr class="table-light">
                        <td colspan="5" class="fw-semibold">{{ $filters['from'] ? __('Balance on :date', ['date' => fmt_date($filters['from'])]) : __('Opening balance') }}</td>
                        <td class="text-end fw-semibold"><x-money :value="$ledger['opening']" /></td>
                        <td></td>
                    </tr>
                @endif
                @forelse ($ledger['rows'] as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row->entry_date) }}</td>
                        <td>
                            @if ($row->type === 'expense')
                                <span class="badge text-bg-warning">{{ __('Credit purchase') }}</span>
                            @else
                                <span class="badge text-bg-success">{{ __('Payment') }}</span>
                            @endif
                        </td>
                        <td class="small">
                            {{ $row->detail }}
                            @if ($row->note)<div class="text-body-secondary">{{ \Illuminate\Support\Str::limit($row->note, 80) }}</div>@endif
                        </td>
                        <td class="text-end">@if ($row->debit > 0)<x-money :value="$row->debit" />@endif</td>
                        <td class="text-end">@if ($row->credit > 0)<x-money :value="$row->credit" />@endif</td>
                        <td class="text-end fw-semibold"><x-money :value="$row->balance" /></td>
                        <td class="text-end text-nowrap">
                            @if ($row->type === 'expense')
                                <a href="{{ route('expenses.show', $row->id) }}" class="btn btn-link btn-sm py-0" title="{{ __('View') }}"><i class="bi bi-eye"></i></a>
                            @else
                                <a href="{{ route('vendor-payments.edit', $row->id) }}" class="btn btn-link btn-sm py-0" title="{{ __('Edit payment') }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No credit purchases or payments in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td colspan="3" class="text-end">{{ __('Period total') }}</td>
                    <td class="text-end"><x-money :value="$ledger['debit']" /></td>
                    <td class="text-end"><x-money :value="$ledger['credit']" /></td>
                    <td class="text-end"><x-money :value="$ledger['closing']" /></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if ($ledger['rows']->hasPages())<div class="card-footer">{{ $ledger['rows']->links() }}</div>@endif
</div>
<p class="form-text mt-2">{{ __('Only credit (unpaid) purchases and payments appear here. Expenses paid on the spot do not change what we owe the vendor.') }}</p>
@endsection
