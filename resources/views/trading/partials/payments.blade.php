{{-- Payments linked to one purchase / sale. Needs: $payments, $total (amount due on the document), $createUrl (or null). --}}
@php($paid = \App\Support\Money::sum($payments->pluck('amount')))
@php($due = \App\Support\Money::sub($total, $paid))
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-cash-stack me-1"></i>{{ __('Payments') }}</span>
        @if ($createUrl)
            @can('animal_trading.manage')
                <a href="{{ $createUrl }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Record payment') }}</a>
            @endcan
        @endif
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($payment->payment_date) }}</td>
                        <td>{{ $payment->paymentMode->name }}@if ($payment->reference_no) · {{ $payment->reference_no }}@endif</td>
                        <td class="text-end"><x-money :value="$payment->amount" /></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-body-secondary">{{ __('No payments yet.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-group-divider">
                <tr><td colspan="2" class="text-end">{{ __('Paid') }}</td><td class="text-end fw-semibold"><x-money :value="$paid" /></td></tr>
                <tr><td colspan="2" class="text-end">{{ __('Balance due') }}</td><td class="text-end fw-bold"><x-money :value="$due" /></td></tr>
            </tfoot>
        </table>
    </div>
</div>
