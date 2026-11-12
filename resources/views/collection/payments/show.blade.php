@extends('layouts.app')

@section('title', __('Cycle :no', ['no' => $cycle->cycle_no]))

@php
    $sum = fn (string $field) => \App\Support\Money::sum($statements->pluck($field));
    $pending = $statements->reject->isPaid();
@endphp

@section('page-actions')
    <a href="{{ route('farmer-payments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Farmer payments') }}</a>
    @unless ($cycle->isDraft())
        <a href="{{ route('farmer-payments.bank-sheet', $cycle) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Bank sheet') }}</a>
    @endunless
    @if ($canManage && $cycle->isDraft())
        <x-confirm-form :action="route('farmer-payments.finalize', $cycle)" method="POST" :label="__('Finalize')" icon="bi-check2-circle"
                        button-class="btn btn-success btn-sm" :confirm-button="__('Finalize')"
                        :message="__('Finalize :no? Statements become read-only and can then be paid.', ['no' => $cycle->cycle_no])" />
        <x-confirm-form :action="route('farmer-payments.destroy', $cycle)" reason :label="__('Delete draft')"
                        :message="__('Delete draft :no? Its milk becomes editable again.', ['no' => $cycle->cycle_no])" />
    @endif
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-4 align-items-center">
        <div>{{ __('Period') }}: <strong>{{ fmt_date($cycle->from_date) }} – {{ fmt_date($cycle->to_date) }}</strong></div>
        <div><span @class(['badge', 'text-bg-warning' => $cycle->isDraft(), 'text-bg-success' => ! $cycle->isDraft()])>{{ $cycle->status->label() }}</span>
            @if ($cycle->finalized_at)<span class="small text-body-secondary">{{ fmt_date($cycle->finalized_at) }} · {{ $cycle->finalizer?->name }}</span>@endif</div>
        <div>{{ __('Litres') }}: <strong>{{ fmt_qty($sum('total_qty')) }}</strong></div>
        <div>{{ __('Gross') }}: <strong>{{ inr($sum('gross_amount')) }}</strong></div>
        <div>{{ __('Net payable') }}: <strong>{{ inr($sum('net_payable')) }}</strong></div>
        <div>{{ __('Still to pay') }}: <strong>{{ inr(\App\Support\Money::sum($pending->pluck('net_payable'))) }}</strong></div>
        @if ($canManage && ! $cycle->isDraft() && $pending->isNotEmpty())
            <form method="POST" action="{{ route('farmer-payments.pay-all', $cycle) }}" class="d-flex gap-2 align-items-end ms-auto">
                @csrf
                <x-form.date name="paid_on" id="pay_all_on" :value="today()" max="today" group-class="mb-0" class="form-control-sm" />
                <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm(@js(__('Mark all :n pending statements paid in cash?', ['n' => $pending->count()])))">
                    <i class="bi bi-cash-stack me-1"></i>{{ __('Mark all paid (cash)') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Farmer') }}</th>
                    <th class="text-end">{{ __('Litres') }}</th>
                    <th class="text-end">{{ __('Fat / SNF') }}</th>
                    <th class="text-end">{{ __('Gross') }}</th>
                    <th class="text-end">{{ __('Bonus') }}</th>
                    <th class="text-end">{{ __('Advance') }}</th>
                    <th class="text-end">{{ __('Other') }}</th>
                    <th class="text-end">{{ __('Net') }}</th>
                    <th>{{ __('Payment') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($statements as $s)
                    <tr>
                        <td>
                            <span class="font-monospace small">{{ $s->farmer->code }}</span> {{ $s->farmer->name }}
                            <a href="{{ route('farmer-statements.pdf', $s) }}" target="_blank" class="ms-1" aria-label="{{ __('Statement PDF') }}" title="{{ __('Statement PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                            @if ($canManage && $cycle->isDraft())
                                <a href="{{ route('farmer-statements.edit', $s) }}" class="ms-1" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                        <td class="text-end">{{ fmt_qty($s->total_qty) }}</td>
                        <td class="text-end small">{{ $s->avg_fat ?? '—' }} / {{ $s->avg_snf ?? '—' }}</td>
                        <td class="text-end">{{ inr($s->gross_amount) }}</td>
                        <td class="text-end">{{ inr($s->bonus) }}</td>
                        <td class="text-end">{{ inr($s->advance_deduction) }}</td>
                        <td class="text-end" title="{{ $s->deduction_note }}">{{ inr($s->other_deduction) }}</td>
                        <td class="text-end fw-semibold">{{ inr($s->net_payable) }}</td>
                        <td class="text-nowrap">
                            @if ($s->isPaid())
                                <span class="badge text-bg-success">{{ __('Paid') }}</span>
                                <span class="small">{{ fmt_date($s->paid_on) }} · {{ $s->paymentMode?->name }}@if ($s->reference_no) · {{ $s->reference_no }}@endif</span>
                                @if ($canManage)
                                    <x-confirm-form :action="route('farmer-statements.unpay', $s)" method="POST" reason :label="__('Undo')" icon="bi-arrow-counterclockwise"
                                                    button-class="btn btn-link btn-sm p-0 ms-1" :message="__('Undo the payment of :code?', ['code' => $s->farmer->code])" />
                                @endif
                            @elseif ($cycle->isDraft())
                                <span class="badge text-bg-secondary">{{ __('Draft') }}</span>
                            @elseif ($canManage)
                                <form method="POST" action="{{ route('farmer-statements.pay', $s) }}" class="d-flex gap-1">
                                    @csrf
                                    <input type="hidden" name="paid_on" value="{{ today()->toDateString() }}">
                                    <select name="payment_mode_id" class="form-select form-select-sm" required aria-label="{{ __('Paid by') }}">
                                        @foreach ($paymentModes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </select>
                                    <input type="text" name="reference_no" class="form-control form-control-sm" maxlength="50" placeholder="{{ __('Ref') }}" aria-label="{{ __('Reference') }}">
                                    <button type="submit" class="btn btn-success btn-sm">{{ __('Pay') }}</button>
                                </form>
                            @else
                                <span class="badge text-bg-warning">{{ __('Pending') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="fw-semibold">
                <tr>
                    <td>{{ __('Total (:n)', ['n' => $statements->count()]) }}</td>
                    <td class="text-end">{{ fmt_qty($sum('total_qty')) }}</td>
                    <td></td>
                    <td class="text-end">{{ inr($sum('gross_amount')) }}</td>
                    <td class="text-end">{{ inr($sum('bonus')) }}</td>
                    <td class="text-end">{{ inr($sum('advance_deduction')) }}</td>
                    <td class="text-end">{{ inr($sum('other_deduction')) }}</td>
                    <td class="text-end">{{ inr($sum('net_payable')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
