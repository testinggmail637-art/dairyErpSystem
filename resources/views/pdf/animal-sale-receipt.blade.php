@extends('pdf.layout')

@section('title', __('Sale receipt').' '.$sale->sale_no)

@section('content')
    @php($paid = \App\Support\Money::sum($sale->payments->pluck('amount')))
    <table style="width: 100%; margin-bottom: 6px;">
        <tr>
            <td style="vertical-align: top;">
                {{ __('Sold to') }}:<br>
                <strong>{{ $sale->party->name }}</strong><br>
                @if ($sale->party->village){{ $sale->party->village }}<br>@endif
                @if ($sale->party->address){{ $sale->party->address }}<br>@endif
                @if ($sale->party->phone){{ $sale->party->phone }}@endif
            </td>
            <td class="text-end" style="vertical-align: top;">
                {{ __('Receipt no.') }}: <strong>{{ $sale->sale_no }}</strong><br>
                {{ __('Sale date') }}: {{ fmt_date($sale->sale_date) }}
                @if ($sale->isCancelled())<br><strong>{{ __('CANCELLED') }}</strong> — {{ $sale->cancel_reason }}@endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('Tag no.') }}</th>
                <th>{{ __('Animal') }}</th>
                <th>{{ __('Breed') }}</th>
                <th>{{ __('Gender / stage') }}</th>
                <th class="text-end">{{ __('Price') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->animal->tag_no }}</td>
                    <td>{{ $item->animal->animal_type->label() }}@if ($item->animal->name) · {{ $item->animal->name }}@endif</td>
                    <td>{{ $item->animal->breed?->name ?? '—' }}</td>
                    <td>{{ $item->animal->gender->label() }} · {{ $item->animal->lifecycle_stage->label() }}</td>
                    <td class="text-end">{{ inr($item->price) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">{{ __('Total sale price') }}</td>
                <td class="text-end">{{ inr($sale->total_price) }}</td>
            </tr>
        </tfoot>
    </table>

    <table style="width: 45%; margin: 10px 0 0 auto; border-collapse: collapse;">
        @unless (\App\Support\Money::isZero($sale->selling_costs))
            <tr><td>{{ __('Less: selling costs') }}</td><td class="text-end">− {{ inr($sale->selling_costs) }}</td></tr>
            <tr><td>{{ __('Net amount') }}</td><td class="text-end">{{ inr($sale->net_amount) }}</td></tr>
        @endunless
        <tr><td>{{ __('Received from buyer') }}</td><td class="text-end">{{ inr($paid) }}</td></tr>
        <tr><td><strong>{{ __('Balance due') }}</strong></td>
            <td class="text-end"><strong>{{ inr($sale->isCancelled() ? '0' : \App\Support\Money::sub($sale->net_amount, $paid)) }}</strong></td></tr>
    </table>

    @if ($sale->payments->isNotEmpty())
        <table class="data" style="width: 60%; margin-top: 14px;">
            <thead>
                <tr><th>{{ __('Payment date') }}</th><th>{{ __('Mode / reference') }}</th><th class="text-end">{{ __('Amount') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($sale->payments as $payment)
                    <tr>
                        <td>{{ fmt_date($payment->payment_date) }}</td>
                        <td>{{ $payment->paymentMode->name }}@if ($payment->reference_no) · {{ $payment->reference_no }}@endif</td>
                        <td class="text-end">{{ inr($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table style="width: 100%; margin-top: 50px;">
        <tr>
            <td>{{ __('Buyer signature') }}</td>
            <td class="text-end">{{ __('For') }} {{ $appearance['app_name'] }}</td>
        </tr>
    </table>
@endsection
