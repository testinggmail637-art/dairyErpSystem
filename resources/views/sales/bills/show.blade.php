@extends('layouts.app')

@section('title', __('Bill :no', ['no' => $bill->label()]))

@php($b = $balances ?? $bill->only(['previous_balance', 'payments_in_period', 'adjustments_in_period', 'closing_balance']))

@section('page-actions')
    <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Bills') }}</a>
    <a href="{{ route('bills.pdf', $bill) }}" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}</a>
    @if ($bill->isDraft())
        @can('bills.finalize')
            <x-confirm-form :action="route('bills.finalize', $bill)" method="POST" :message="__('Finalize this bill? It gets a number and can no longer be changed.')"
                            :label="__('Finalize')" icon="bi-lock" button-class="btn btn-success btn-sm" :confirm-button="__('Finalize')" />
        @endcan
        @can('bills.generate')
            <x-confirm-form :action="route('bills.destroy', $bill)" :label="__('Delete draft')" :message="__('Delete this draft? Its deliveries can be billed again.')" />
        @endcan
    @elseif ($bill->status === \App\Enums\BillStatus::Finalized)
        @can('bills.cancel')
            <x-confirm-form :action="route('bills.cancel', $bill)" method="POST" reason :label="__('Cancel bill')" icon="bi-x-circle"
                            :message="__('Cancel bill :no? Its deliveries can be billed again.', ['no' => $bill->label()])" :confirm-button="__('Cancel bill')" />
        @endcan
    @endif
@endsection

@section('content')
@if ($bill->isDraft() && $pendingInPeriod > 0)
    <div class="alert alert-warning">{{ __(':n deliveries of this customer in the period are still pending and are not on this bill. Mark them first, then delete and generate the draft again.', ['n' => $pendingInPeriod]) }}</div>
@endif
@if ($bill->status === \App\Enums\BillStatus::Cancelled)
    <div class="alert alert-secondary">{{ __('Cancelled on :date by :user: :reason', ['date' => fmt_datetime($bill->cancelled_at), 'user' => $bill->cancelledBy?->name, 'reason' => $bill->cancel_reason]) }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><strong>{{ $bill->customer->name }}</strong> <span class="small font-monospace text-body-secondary">{{ $bill->customer->code }}</span></span>
                <span class="badge {{ $bill->status->badge() }}">{{ $bill->status->label() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Product') }}</th>
                            <th class="text-end">{{ __('Morning (L)') }}</th>
                            <th class="text-end">{{ __('Evening (L)') }}</th>
                            <th class="text-end">{{ __('Rate') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ fmt_date($row['date']) }}</td>
                                <td>{{ $row['product'] }}</td>
                                <td class="text-end">{{ \App\Support\Money::isZero($row['morning']) ? '' : fmt_qty($row['morning']) }}</td>
                                <td class="text-end">{{ \App\Support\Money::isZero($row['evening']) ? '' : fmt_qty($row['evening']) }}</td>
                                <td class="text-end"><x-money :value="$row['rate']" /></td>
                                <td class="text-end"><x-money :value="$row['amount']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-6">{{ __('Period') }}</dt><dd class="col-6 text-end">{{ fmt_date($bill->period_from) }} – {{ fmt_date($bill->period_to) }}</dd>
                    <dt class="col-6">{{ __('Bill date') }}</dt><dd class="col-6 text-end">{{ fmt_date($bill->bill_date) }}</dd>
                    <dt class="col-6">{{ __('Due date') }}</dt><dd class="col-6 text-end">{{ fmt_date($bill->due_date) }}</dd>
                    <dt class="col-6 border-top pt-2">{{ __('Previous balance') }}</dt><dd class="col-6 text-end border-top pt-2"><x-money :value="$b['previous_balance']" /></dd>
                    <dt class="col-6">{{ __('This bill') }}</dt><dd class="col-6 text-end"><x-money :value="$bill->total" /></dd>
                    <dt class="col-6">{{ __('Payments') }}</dt><dd class="col-6 text-end">− <x-money :value="$b['payments_in_period']" /></dd>
                    @unless (\App\Support\Money::isZero($b['adjustments_in_period']))
                        <dt class="col-6">{{ __('Adjustments') }}</dt><dd class="col-6 text-end"><x-money :value="$b['adjustments_in_period']" /></dd>
                    @endunless
                    <dt class="col-6 border-top pt-2 fs-6">{{ __('Closing balance') }}</dt><dd class="col-6 text-end border-top pt-2 fs-6 fw-bold"><x-money :value="$b['closing_balance']" /></dd>
                </dl>
                @if ($bill->isDraft())<p class="form-text mb-0 mt-2">{{ __('Balances are worked out again at finalize.') }}</p>@endif
                @if ($bill->finalized_at)<p class="form-text mb-0 mt-2">{{ __('Finalized :date by :user', ['date' => fmt_datetime($bill->finalized_at), 'user' => $bill->finalizedBy?->name]) }}</p>@endif
            </div>
        </div>
    </div>
</div>
@endsection
