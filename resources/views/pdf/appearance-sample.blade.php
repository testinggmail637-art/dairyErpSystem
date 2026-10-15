@extends('pdf.layout')

@section('title', __('Sample bill'))

@section('content')
    <p class="muted">{{ __('This sample shows how bills, statements and receipts will look with the current Settings → Appearance (logo, header, accent colour, footer).') }}</p>

    <table style="width: 100%; margin-top: 6px;">
        <tr>
            <td><strong>{{ __('Bill to') }}:</strong> Sharma Ji, Gandhi Nagar</td>
            <td class="text-end"><strong>{{ __('Bill no.') }}</strong> BILL/2026-27/0001 · <strong>{{ __('Date') }}</strong> {{ fmt_date(now()) }}</td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty (L)') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Amount') }}</th></tr>
        </thead>
        <tbody>
            <tr><td>{{ __('Cow Milk') }}</td><td class="text-end">{{ fmt_qty('60') }}</td><td class="text-end">{{ inr('60') }}</td><td class="text-end">{{ inr('3600') }}</td></tr>
            <tr><td>{{ __('Buffalo Milk') }}</td><td class="text-end">{{ fmt_qty('15.5') }}</td><td class="text-end">{{ inr('70') }}</td><td class="text-end">{{ inr('1085') }}</td></tr>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end">{{ __('Total') }}</td><td class="text-end">{{ inr('4685') }}</td></tr>
        </tfoot>
    </table>
@endsection
