@extends('pdf.layout')

@section('title', __('Bill').' '.$bill->label())

@section('content')
    <table style="width: 100%; margin-bottom: 6px;">
        <tr>
            <td style="vertical-align: top;">
                <strong>{{ $bill->customer->name }}</strong> ({{ $bill->customer->code }})<br>
                @if ($bill->customer->address){{ $bill->customer->address }}@if ($bill->customer->area), {{ $bill->customer->area }}@endif<br>@endif
                @if ($bill->customer->phone){{ $bill->customer->phone }}<br>@endif
                @if ($bill->customer->gstin)GSTIN: {{ $bill->customer->gstin }}@endif
            </td>
            <td class="text-end" style="vertical-align: top;">
                {{ __('Period') }}: {{ fmt_date($bill->period_from) }} – {{ fmt_date($bill->period_to) }}<br>
                {{ __('Bill date') }}: {{ fmt_date($bill->bill_date) }}<br>
                <strong>{{ __('Due date') }}: {{ fmt_date($bill->due_date) }}</strong>
                @if ($bill->isDraft())<br><strong>{{ __('DRAFT — not final') }}</strong>@endif
                @if ($bill->status === \App\Enums\BillStatus::Cancelled)<br><strong>{{ __('CANCELLED') }}</strong>@endif
            </td>
        </tr>
    </table>

    <table class="data">
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
                    <td class="text-end">{{ inr($row['rate']) }}</td>
                    <td class="text-end">{{ inr($row['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="text-end">{{ __('This bill') }}</td><td class="text-end">{{ inr($bill->total) }}</td></tr>
        </tfoot>
    </table>

    <table style="width: 45%; margin: 10px 0 0 auto; border-collapse: collapse;">
        <tr><td>{{ __('Previous balance') }}</td><td class="text-end">{{ inr($b['previous_balance']) }}</td></tr>
        <tr><td>{{ __('This bill') }}</td><td class="text-end">{{ inr($bill->total) }}</td></tr>
        <tr><td>{{ __('Payments received') }}</td><td class="text-end">− {{ inr($b['payments_in_period']) }}</td></tr>
        @unless (\App\Support\Money::isZero($b['adjustments_in_period']))
            <tr><td>{{ __('Adjustments') }}</td><td class="text-end">{{ inr($b['adjustments_in_period']) }}</td></tr>
        @endunless
        <tr><td style="border-top: 2px solid #222; padding-top: 4px;"><strong>{{ __('Amount due') }}</strong></td>
            <td class="text-end" style="border-top: 2px solid #222; padding-top: 4px;"><strong>{{ inr($b['closing_balance']) }}</strong></td></tr>
    </table>
@endsection
