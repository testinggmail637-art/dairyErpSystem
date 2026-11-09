@extends('layouts.app')

@section('title', $farmer->code.' · '.$farmer->name)

@section('page-actions')
    <a href="{{ route('farmers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Farmers') }}</a>
    @can('farmers.manage')
        <a href="{{ route('farmers.edit', $farmer) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
        <x-confirm-form :action="route('farmers.destroy', $farmer)" reason :label="__('Delete')" :message="__('Delete farmer :name?', ['name' => $farmer->name])" />
    @endcan
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('Farmer') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Code') }}</dt><dd class="col-7 font-monospace">{{ $farmer->code }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Name') }}</dt><dd class="col-7">{{ $farmer->name }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Father\'s name') }}</dt><dd class="col-7">{{ $farmer->father_name ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Mobile') }}</dt><dd class="col-7">{{ $farmer->phone ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Village') }}</dt><dd class="col-7">{{ $farmer->village ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Address') }}</dt><dd class="col-7">{{ $farmer->address ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Usual milk') }}</dt><dd class="col-7">{{ $farmer->default_milk_type->label() }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Rate chart') }}</dt>
                    <dd class="col-7">@if ($farmer->rateChart)@can('rate_charts.manage')<a href="{{ route('rate-charts.show', $farmer->rateChart) }}">{{ $farmer->rateChart->name }}</a>@else{{ $farmer->rateChart->name }}@endcan @else{{ __('Default chart of the milk') }}@endif</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Status') }}</dt>
                    <dd class="col-7">{!! $farmer->is_active ? '<span class="badge text-bg-success">'.e(__('Active')).'</span>' : '<span class="badge text-bg-secondary">'.e(__('Inactive')).'</span>' !!}</dd>
                    @if ($farmer->notes)
                        <dt class="col-5 fw-normal text-body-secondary">{{ __('Notes') }}</dt><dd class="col-7">{{ $farmer->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-semibold">{{ __('Payment details') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Account holder') }}</dt><dd class="col-7">{{ $farmer->bank_account_name ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Account number') }}</dt><dd class="col-7 font-monospace">{{ $farmer->bank_account_no ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('IFSC') }}</dt><dd class="col-7 font-monospace">{{ $farmer->ifsc ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('UPI ID') }}</dt><dd class="col-7">{{ $farmer->upi_id ?: '—' }}</dd>
                    <dt class="col-5 fw-normal text-body-secondary">{{ __('Opening balance') }}</dt>
                    <dd class="col-7"><x-money :value="$farmer->opening_balance" /> <span class="small text-body-secondary">{{ __('(+ = we owe the farmer)') }}</span></dd>
                    @if ($balance !== null)
                        <dt class="col-5 fw-normal text-body-secondary">{{ __('Balance now') }}</dt>
                        <dd class="col-7 fw-semibold"><x-money :value="$balance" /> <span class="small text-body-secondary fw-normal">{{ __('opening + finalized statements − advances − paid') }}</span></dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    @if ($canAdvance)
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-cash-coin me-1"></i>{{ __('Advances') }}</span>
                    <span>{{ __('Outstanding') }}: <x-money :value="$outstandingAdvance" /></span>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12 col-lg-5">
                        @include('collection.advances._form', ['farmerOptions' => [], 'farmerId' => $farmer->id])
                    </div>
                    <div class="col-12 col-lg-7">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>{{ __('Date') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Paid by') }}</th><th>{{ __('Note') }}</th></tr></thead>
                            <tbody>
                                @forelse ($advances as $advance)
                                    <tr>
                                        <td>{{ fmt_date($advance->advance_date) }}</td>
                                        <td class="text-end">{{ inr($advance->amount) }}</td>
                                        <td class="small">{{ $advance->paymentMode?->name }}</td>
                                        <td class="small">{{ $advance->note }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-body-secondary">{{ __('No advances yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <a href="{{ route('farmer-advances.index', ['farmer_id' => $farmer->id, 'from' => '2000-01-01']) }}" class="small">{{ __('All advances of this farmer') }}</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
