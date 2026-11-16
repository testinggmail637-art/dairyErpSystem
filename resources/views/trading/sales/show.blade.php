@extends('layouts.app')

@section('title', __('Sale :no', ['no' => $sale->sale_no]))

@section('page-actions')
    <a href="{{ route('animal-sales.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to sales') }}</a>
    @if (! $sale->isCancelled())
        @can('animal_trading.manage')
            <x-confirm-form :action="route('animal-sales.cancel', $sale)" method="POST" reason :label="__('Cancel sale')" icon="bi-x-circle"
                            :confirm-button="__('Cancel sale')"
                            :message="__('Cancel sale :no? Its animals become active again.', ['no' => $sale->sale_no])" />
        @endcan
    @endif
@endsection

@section('content')
@if ($sale->isCancelled())
    <div class="alert alert-secondary">
        <i class="bi bi-x-circle me-1"></i>
        {{ __('Cancelled on :date by :user.', ['date' => fmt_datetime($sale->cancelled_at), 'user' => $sale->canceller?->name ?? '—']) }}
        {{ __('Reason') }}: {{ $sale->cancel_reason }}
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Sale no.') }}</dt>
            <dd class="col-sm-9">{{ $sale->sale_no }}
                <span class="badge ms-1 {{ $sale->isCancelled() ? 'text-bg-secondary' : 'text-bg-success' }}">{{ $sale->status->label() }}</span></dd>
            <dt class="col-sm-3">{{ __('Date') }}</dt><dd class="col-sm-9">{{ fmt_date($sale->sale_date) }}</dd>
            <dt class="col-sm-3">{{ __('Buyer (party)') }}</dt>
            <dd class="col-sm-9">{{ $sale->party->name }}@if ($sale->party->phone) · {{ $sale->party->phone }}@endif</dd>
            @if ($sale->notes)<dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{{ $sale->notes }}</dd>@endif
            <dt class="col-sm-3">{{ __('Entered by') }}</dt><dd class="col-sm-9">{{ $sale->creator?->name ?? '—' }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Animal') }}</th>
                    <th>{{ __('Type / breed') }}</th>
                    <th>{{ __('Ownership') }}</th>
                    <th class="text-end">{{ __('Price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>
                            @if ($item->animal->trashed())
                                {{ $item->animal->label() }} <span class="badge text-bg-secondary">{{ __('Deleted') }}</span>
                            @else
                                <a href="{{ route('animals.show', $item->animal) }}" class="fw-semibold">{{ $item->animal->label() }}</a>
                            @endif
                        </td>
                        <td>{{ $item->animal->animal_type->label() }}@if ($item->animal->breed) · {{ $item->animal->breed->name }}@endif</td>
                        <td>{{ $item->animal->ownership_type->label() }}</td>
                        <td class="text-end"><x-money :value="$item->price" /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-group-divider">
                <tr>
                    <td colspan="3" class="text-end">{{ __('Sale price') }}</td>
                    <td class="text-end fw-semibold"><x-money :value="$sale->total_price" /></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end">{{ __('Less: selling costs') }}</td>
                    <td class="text-end"><x-money :value="$sale->selling_costs" /></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-end fw-semibold">{{ __('Net amount') }}</td>
                    <td class="text-end fw-bold fs-5"><x-money :value="$sale->net_amount" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@include('trading.partials.payments', ['payments' => $sale->payments, 'total' => $sale->isCancelled() ? '0' : $sale->net_amount, 'createUrl' => $sale->isCancelled() ? null : route('animal-payments.create', ['party_id' => $sale->party_id, 'direction' => 'received', 'animal_sale_id' => $sale->id])])
@endsection
