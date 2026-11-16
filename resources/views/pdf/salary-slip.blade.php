@extends('pdf.layout')

@section('title', __('Salary slip').' · '.$slip->run->label())

@section('content')
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <td style="vertical-align: top;">
                <strong>{{ $slip->staffMember->name }}</strong><br>
                {{ $slip->staffMember->designation }}<br>
                @if ($slip->staffMember->phone){{ $slip->staffMember->phone }}<br>@endif
                {{ __('Joined') }}: {{ fmt_date($slip->staffMember->joining_date) }}
            </td>
            <td class="text-end" style="vertical-align: top;">
                {{ __('Month') }}: <strong>{{ $slip->run->label() }}</strong><br>
                {{ __('Run no.') }}: {{ $slip->run->run_no }}<br>
                @if (! $slip->run->isFinalized())<strong>{{ __('DRAFT — not final') }}</strong>
                @elseif ($slip->paid_on){{ __('Paid') }}: {{ fmt_date($slip->paid_on) }} · {{ $slip->paymentMode?->name }}
                @else<strong>{{ __('Payment pending') }}</strong>@endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Days in month') }}</th><th>{{ __('Present') }}</th><th>{{ __('Half day') }}</th><th>{{ __('Leave') }}</th>
                <th>{{ __('Holiday') }}</th><th>{{ __('Absent') }}</th><th class="text-end">{{ __('Paid days') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $slip->days_in_month }}</td><td>{{ $slip->present_days }}</td><td>{{ $slip->half_days }}</td><td>{{ $slip->leave_days }}</td>
                <td>{{ $slip->holiday_days }}</td><td>{{ $slip->absent_days }}</td><td class="text-end">{{ rtrim(rtrim($slip->payable_days, '0'), '.') }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 55%; margin: 14px 0 0 auto; border-collapse: collapse;">
        <tr><td>{{ __('Monthly salary') }}</td><td class="text-end">{{ inr($slip->base_salary) }}</td></tr>
        <tr><td>{{ __('Earned (salary × paid days ÷ days in month)') }}</td><td class="text-end">{{ inr($slip->earned_salary) }}</td></tr>
        <tr><td>{{ __('Bonus') }}</td><td class="text-end">+ {{ inr($slip->bonus) }}</td></tr>
        <tr><td>{{ __('Advance recovered') }}</td><td class="text-end">− {{ inr($slip->advance_deduction) }}</td></tr>
        <tr><td>{{ __('Other deduction') }}@if ($slip->note) <span class="muted">({{ $slip->note }})</span>@endif</td><td class="text-end">− {{ inr($slip->other_deduction) }}</td></tr>
        <tr><td style="padding-top: 4px;"><strong>{{ __('Net salary') }}</strong></td><td class="text-end" style="padding-top: 4px;"><strong>{{ inr($slip->net_salary) }}</strong></td></tr>
    </table>

    <table style="width: 100%; margin-top: 50px;">
        <tr>
            <td>{{ __('Employee signature') }}</td>
            <td class="text-end">{{ __('For') }} {{ $appearance['app_name'] }}</td>
        </tr>
    </table>
@endsection
