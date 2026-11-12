@extends('pdf.layout')

@section('title', __('Milk statement').' '.$s->cycle->cycle_no)

@section('content')
    <table style="width: 100%; margin-bottom: 6px;">
        <tr>
            <td style="vertical-align: top;">
                <strong>{{ $s->farmer->name }}</strong> ({{ $s->farmer->code }})<br>
                @if ($s->farmer->village){{ $s->farmer->village }}<br>@endif
                @if ($s->farmer->phone){{ $s->farmer->phone }}<br>@endif
                @if ($s->farmer->bank_account_no){{ __('A/c') }}: {{ $s->farmer->bank_account_no }}@if ($s->farmer->ifsc) · {{ $s->farmer->ifsc }}@endif @endif
            </td>
            <td class="text-end" style="vertical-align: top;">
                {{ __('Period') }}: {{ fmt_date($s->cycle->from_date) }} – {{ fmt_date($s->cycle->to_date) }}<br>
                @if ($s->cycle->isDraft())<strong>{{ __('DRAFT — not final') }}</strong>
                @elseif ($s->isPaid()){{ __('Paid') }}: {{ fmt_date($s->paid_on) }} · {{ $s->paymentMode?->name }}@if ($s->reference_no) · {{ $s->reference_no }}@endif
                @else<strong>{{ __('Payment pending') }}</strong>@endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Shift') }}</th>
                <th>{{ __('Milk') }}</th>
                <th class="text-end">{{ __('Litres') }}</th>
                <th class="text-end">{{ __('Fat') }}</th>
                <th class="text-end">{{ __('SNF') }}</th>
                <th class="text-end">{{ __('Rate') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ fmt_date($row->collection_date) }}</td>
                    <td>{{ $row->shift->label() }}</td>
                    <td>{{ $row->milk_type->label() }}</td>
                    <td class="text-end">{{ fmt_qty($row->quantity_litre) }}</td>
                    <td class="text-end">{{ $row->fat ?? '—' }}</td>
                    <td class="text-end">{{ $row->snf ?? '—' }}</td>
                    <td class="text-end">{{ inr($row->rate_per_litre) }}</td>
                    <td class="text-end">{{ inr($row->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">{{ __('Total') }}</td>
                <td class="text-end">{{ fmt_qty($s->total_qty) }}</td>
                <td class="text-end">{{ $s->avg_fat ?? '' }}</td>
                <td class="text-end">{{ $s->avg_snf ?? '' }}</td>
                <td></td>
                <td class="text-end">{{ inr($s->gross_amount) }}</td>
            </tr>
        </tfoot>
    </table>

    <table style="width: 45%; margin: 10px 0 0 auto; border-collapse: collapse;">
        <tr><td>{{ __('Milk amount') }}</td><td class="text-end">{{ inr($s->gross_amount) }}</td></tr>
        <tr><td>{{ __('Bonus') }}</td><td class="text-end">+ {{ inr($s->bonus) }}</td></tr>
        <tr><td>{{ __('Advance recovered') }}</td><td class="text-end">− {{ inr($s->advance_deduction) }}</td></tr>
        <tr><td>{{ __('Other deduction') }}@if ($s->deduction_note) <span class="muted">({{ $s->deduction_note }})</span>@endif</td>
            <td class="text-end">− {{ inr($s->other_deduction) }}</td></tr>
        <tr><td style="border-top: 2px solid #222; padding-top: 4px;"><strong>{{ __('Net payable') }}</strong></td>
            <td class="text-end" style="border-top: 2px solid #222; padding-top: 4px;"><strong>{{ inr($s->net_payable) }}</strong></td></tr>
    </table>
@endsection
