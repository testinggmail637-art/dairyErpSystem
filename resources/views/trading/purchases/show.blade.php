@extends('layouts.app')

@section('title', __('Purchase :no', ['no' => $purchase->purchase_no]))

@section('page-actions')
    <a href="{{ route('animal-purchases.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to purchases') }}</a>
    @can('animal_trading.manage')
        <x-confirm-form :action="route('animal-purchases.destroy', $purchase)" :label="__('Delete')" reason
                        :message="__('Delete purchase :no? Its animals are deleted too. Only possible while they have no other records.', ['no' => $purchase->purchase_no])" />
    @endcan
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Purchase no.') }}</dt><dd class="col-sm-9">{{ $purchase->purchase_no }}</dd>
            <dt class="col-sm-3">{{ __('Date') }}</dt><dd class="col-sm-9">{{ fmt_date($purchase->purchase_date) }}</dd>
            <dt class="col-sm-3">{{ __('Seller (party)') }}</dt>
            <dd class="col-sm-9">{{ $purchase->party->name }}@if ($purchase->party->phone) · {{ $purchase->party->phone }}@endif</dd>
            @if ($purchase->notes)<dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{{ $purchase->notes }}</dd>@endif
            <dt class="col-sm-3">{{ __('Entered by') }}</dt><dd class="col-sm-9">{{ $purchase->creator?->name ?? '—' }}</dd>
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
                    <th>{{ __('Stage') }}</th>
                    <th>{{ __('Ownership') }}</th>
                    <th class="text-end">{{ __('Price') }}</th>
                    <th class="text-end">{{ __('Share of costs') }}</th>
                    <th class="text-end">{{ __('Total cost') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td>
                            @if ($item->animal->trashed())
                                {{ $item->animal->label() }} <span class="badge text-bg-secondary">{{ __('Deleted') }}</span>
                            @else
                                <a href="{{ route('animals.show', $item->animal) }}" class="fw-semibold">{{ $item->animal->label() }}</a>
                            @endif
                        </td>
                        <td>{{ $item->animal->animal_type->label() }}@if ($item->animal->breed) · {{ $item->animal->breed->name }}@endif</td>
                        <td>{{ $item->animal->lifecycle_stage->label() }}</td>
                        <td>{{ $item->animal->ownership_type->label() }}</td>
                        <td class="text-end"><x-money :value="$item->price" /></td>
                        <td class="text-end"><x-money :value="$item->allocated_cost" /></td>
                        <td class="text-end fw-semibold"><x-money :value="$item->total_cost" /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-group-divider">
                <tr>
                    <td colspan="4" class="text-end">{{ __('Animal price') }}</td>
                    <td class="text-end fw-semibold"><x-money :value="$purchase->total_animal_price" /></td>
                    <td colspan="2"></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-end small text-body-secondary">
                        {{ __('Transport') }} <x-money :value="$purchase->transport_cost" /> ·
                        {{ __('Commission') }} <x-money :value="$purchase->commission" /> ·
                        {{ __('Other') }} <x-money :value="$purchase->other_cost" />
                    </td>
                    <td></td>
                    <td class="text-end fw-semibold"><x-money :value="\App\Support\Money::sub($purchase->total_amount, $purchase->total_animal_price)" /></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="6" class="text-end fw-semibold">{{ __('Total amount') }}</td>
                    <td class="text-end fw-bold fs-5"><x-money :value="$purchase->total_amount" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
