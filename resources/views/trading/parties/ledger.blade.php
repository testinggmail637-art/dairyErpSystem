@extends('layouts.app')

@section('title', __('Ledger · :name', ['name' => $party->name]))

@section('page-actions')
    <a href="{{ route('parties.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Parties') }}</a>
    @can('animal_trading.manage')
        <a href="{{ route('animal-payments.create', ['party_id' => $party->id, 'direction' => \App\Support\Money::isNegative($balance) ? 'paid' : 'received']) }}"
           class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Record payment') }}</a>
    @endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="fw-semibold">{{ $party->name }}</div>
                <div class="small text-body-secondary">{{ $party->party_type->label() }}@if ($party->village) · {{ $party->village }}@endif @if ($party->phone) · {{ $party->phone }}@endif</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-body-secondary">{{ __('Current balance') }}</div>
                <div class="fs-4 fw-bold"><x-money :value="$balance" /></div>
                <div class="small">
                    @if (\App\Support\Money::isZero($balance))
                        {{ __('Settled') }}
                    @elseif (\App\Support\Money::isNegative($balance))
                        {{ __('We owe this party') }}
                    @else
                        {{ __('This party owes us') }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('parties.ledger', $party) }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#ledger-results">
            <div class="col-6 col-md-3">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('parties.ledger', $party) }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="ledger-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Entry') }}</th>
                    <th>{{ __('Details') }}</th>
                    <th class="text-end">{{ __('They owe (+)') }}</th>
                    <th class="text-end">{{ __('We owe (−)') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="table-light">
                    <td>{{ $filters['from'] ? fmt_date($filters['from']) : '' }}</td>
                    <td colspan="4" class="fw-semibold">{{ __('Opening balance') }}</td>
                    <td class="text-end fw-semibold"><x-money :value="$ledger['opening']" /></td>
                </tr>
                @forelse ($ledger['rows'] as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row->entry_date) }}</td>
                        <td>
                            @if ($row->type === 'sale')
                                <a href="{{ route('animal-sales.show', $row->id) }}">{{ __('Sale') }}</a>
                            @elseif ($row->type === 'purchase')
                                <a href="{{ route('animal-purchases.show', $row->id) }}">{{ __('Purchase') }}</a>
                            @else
                                {{ app(\App\Services\PartyLedgerService::class)->typeLabel($row->type) }}
                            @endif
                        </td>
                        <td>{{ $row->detail }}</td>
                        <td class="text-end">@unless (\App\Support\Money::isZero($row->debit))<x-money :value="$row->debit" />@endunless</td>
                        <td class="text-end">@unless (\App\Support\Money::isZero($row->credit))<x-money :value="$row->credit" />@endunless</td>
                        <td class="text-end fw-semibold"><x-money :value="$row->balance" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No entries in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-group-divider">
                <tr>
                    <td colspan="3" class="text-end fw-semibold">{{ __('Total / closing') }}</td>
                    <td class="text-end"><x-money :value="$ledger['debit']" /></td>
                    <td class="text-end"><x-money :value="$ledger['credit']" /></td>
                    <td class="text-end fw-bold"><x-money :value="$ledger['closing']" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if ($ledger['rows']->hasPages())<div class="card-footer">{{ $ledger['rows']->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Sales and money we paid raise the balance; purchases and money we received lower it. Cancelled sales are not counted.') }}</p>
@endsection
