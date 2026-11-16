@extends('layouts.app')

@section('title', __('Salary · :month', ['month' => $run->label()]))

@php($m = fn ($col) => \App\Support\Money::sum($run->slips->pluck($col)))

@section('page-actions')
    <a href="{{ route('salary-runs.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Salary runs') }}</a>
    @unless ($run->isFinalized())
        <x-confirm-form :action="route('salary-runs.recalculate', $run)" method="POST" :label="__('Recalculate')" icon="bi-arrow-repeat"
                        button-class="btn btn-outline-primary btn-sm" :confirm-button="__('Recalculate')"
                        :message="__('Read attendance and salaries again? Bonus and other deductions are kept.')" />
        <x-confirm-form :action="route('salary-runs.finalize', $run)" method="POST" :label="__('Finalize')" icon="bi-lock"
                        button-class="btn btn-primary btn-sm" :confirm-button="__('Finalize')"
                        :message="__('Finalize :month? Slips and that month\'s attendance are locked and one salary expense per staff member is created.', ['month' => $run->label()])" />
        <x-confirm-form :action="route('salary-runs.destroy', $run)" :label="__('Delete')" :message="__('Delete this draft salary run?')" />
    @endunless
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-4 align-items-center">
        <div><span class="small text-body-secondary d-block">{{ __('Run no.') }}</span><strong>{{ $run->run_no }}</strong></div>
        <div><span class="small text-body-secondary d-block">{{ __('Status') }}</span>
            <span class="badge {{ $run->isFinalized() ? 'text-bg-success' : 'text-bg-warning' }}">{{ $run->status->label() }}</span>
            @if ($run->isFinalized())<span class="small text-body-secondary">{{ fmt_datetime($run->finalized_at) }} · {{ $run->finalizer?->name }}</span>@endif
        </div>
        <div><span class="small text-body-secondary d-block">{{ __('Earned + bonus (expense)') }}</span><strong><x-money :value="\App\Support\Money::add($m('earned_salary'), $m('bonus'))" /></strong></div>
        <div><span class="small text-body-secondary d-block">{{ __('Advance recovered') }}</span><strong><x-money :value="$m('advance_deduction')" /></strong></div>
        <div><span class="small text-body-secondary d-block">{{ __('Net payable') }}</span><strong class="fs-5"><x-money :value="$m('net_salary')" /></strong></div>
    </div>
    @unless ($run->isFinalized())
        <div class="card-footer small text-body-secondary">
            {{ $paidLeave ? __('Paid days = present + holiday + leave + ½ × half day.') : __('Paid days = present + holiday + ½ × half day (leave is unpaid).') }}
            {{ __('Earned = monthly salary × paid days ÷ days in month.') }}
            {{ __('After changing attendance, press Recalculate.') }}
        </div>
    @endunless
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="small">
                    <th>{{ __('Staff member') }}</th>
                    <th class="text-center" title="{{ __('Present / half day / leave / holiday / absent') }}">P / H / L / HO / A</th>
                    <th class="text-end">{{ __('Paid days') }}</th>
                    <th class="text-end">{{ __('Monthly salary') }}</th>
                    <th class="text-end">{{ __('Earned') }}</th>
                    <th class="text-end">{{ __('Bonus') }}</th>
                    <th class="text-end">{{ __('Advance') }}</th>
                    <th class="text-end">{{ __('Other ded.') }}</th>
                    <th class="text-end">{{ __('Net') }}</th>
                    <th>{{ __('Paid') }}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($run->slips as $slip)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $slip->staffMember->name }}</span>
                            <div class="small text-body-secondary">{{ $slip->staffMember->designation }}@if ($slip->note) · {{ $slip->note }}@endif</div>
                        </td>
                        <td class="text-center text-nowrap small">{{ $slip->present_days }} / {{ $slip->half_days }} / {{ $slip->leave_days }} / {{ $slip->holiday_days }} / {{ $slip->absent_days }}</td>
                        <td class="text-end">{{ rtrim(rtrim($slip->payable_days, '0'), '.') }} / {{ $slip->days_in_month }}</td>
                        <td class="text-end"><x-money :value="$slip->base_salary" /></td>
                        <td class="text-end"><x-money :value="$slip->earned_salary" /></td>
                        <td class="text-end"><x-money :value="$slip->bonus" /></td>
                        <td class="text-end"><x-money :value="$slip->advance_deduction" /></td>
                        <td class="text-end"><x-money :value="$slip->other_deduction" /></td>
                        <td class="text-end fw-semibold"><x-money :value="$slip->net_salary" /></td>
                        <td class="text-nowrap">
                            @if ($slip->paid_on)
                                <span class="badge text-bg-success">{{ fmt_date($slip->paid_on) }}</span>
                                <div class="small text-body-secondary">{{ $slip->paymentMode?->name }}</div>
                            @elseif ($run->isFinalized())
                                <form method="POST" action="{{ route('salary-slips.pay', $slip) }}" class="d-flex gap-1">
                                    @csrf
                                    <input type="date" name="paid_on" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}"
                                           class="form-control form-control-sm" aria-label="{{ __('Paid on') }}" required>
                                    <select name="payment_mode_id" class="form-select form-select-sm" aria-label="{{ __('Payment mode') }}" required>
                                        @foreach ($modes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </select>
                                    <button type="submit" class="btn btn-outline-success btn-sm text-nowrap">{{ __('Mark paid') }}</button>
                                </form>
                            @else
                                <span class="text-body-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @unless ($run->isFinalized())
                                <a href="{{ route('salary-slips.edit', $slip) }}" class="btn btn-outline-primary btn-sm" title="{{ __('Bonus / deductions') }}"><i class="bi bi-pencil"></i></a>
                            @endunless
                            <a href="{{ route('salary-slips.pdf', $slip) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm" title="{{ __('Salary slip PDF') }}"><i class="bi bi-file-earmark-pdf"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-body-secondary py-5">{{ __('No staff employed in this month.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($run->slips->isNotEmpty())
                <tfoot class="table-group-divider fw-semibold">
                    <tr>
                        <td colspan="4" class="text-end">{{ __('Total') }}</td>
                        <td class="text-end"><x-money :value="$m('earned_salary')" /></td>
                        <td class="text-end"><x-money :value="$m('bonus')" /></td>
                        <td class="text-end"><x-money :value="$m('advance_deduction')" /></td>
                        <td class="text-end"><x-money :value="$m('other_deduction')" /></td>
                        <td class="text-end"><x-money :value="$m('net_salary')" /></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
