@extends('layouts.app')

@section('title', ($txn->exists ? __('Edit entry') : ($kind === 'charge' ? __('Add bank charge') : __('Record payment'))).' · '.$loan->loan_no)

@section('page-actions')
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to loan') }}</a>
    @if ($txn->exists)
        @can('delete', $txn)
            <x-confirm-form :action="route('loan-transactions.destroy', $txn)" reason :label="__('Delete')"
                            :message="__('Delete this entry of :amount? Its interest / charge expense is removed too.', ['amount' => inr($txn->amount)])" />
        @endcan
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $txn->exists ? route('loan-transactions.update', $txn) : route('loans.payments.store', $loan) }}" id="payment-form">
                    @csrf
                    @if ($txn->exists) @method('PUT') @else <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid> @endif
                    <input type="hidden" name="kind" value="{{ $kind }}">

                    <div class="row g-3">
                        <div class="col-6">
                            <x-form.date name="txn_date" :label="__('Date')" :value="$txn->txn_date?->toDateString()" max="today" required />
                        </div>
                        <div class="col-6">
                            <x-form.money name="amount" :label="$kind === 'charge' ? __('Charge amount') : __('Amount paid')" :value="$txn->amount" required />
                        </div>
                    </div>

                    @if ($kind === 'payment')
                        <div class="row g-3">
                            <div class="col-6">
                                <x-form.money name="interest_part" :label="__('of which interest')" :value="$txn->exists ? $txn->interest_part : null" />
                            </div>
                            <div class="col-6">
                                <x-form.money name="principal_part" :label="__('of which principal')" :value="$txn->exists ? $txn->principal_part : null" />
                            </div>
                            <div class="col-12 form-text mt-0">{{ __('Filled in for you (oldest instalment first: interest, then principal). Change it to match the bank statement if it differs — the two must add up to the amount.') }}</div>
                        </div>
                    @else
                        <p class="form-text">{{ __('Penal / late charge, foreclosure or part-payment fee, other bank fee — saved as an expense (Finance → Bank Charges), never as interest.') }}</p>
                    @endif

                    <div class="row g-3 mt-0">
                        <div class="col-6">
                            <x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :selected="$txn->payment_mode_id" :placeholder="__('Choose')" required />
                        </div>
                        <div class="col-6">
                            <x-form.input name="reference_no" :label="__('Reference no.')" :value="$txn->reference_no" maxlength="50" :placeholder="__('UTR / cheque no.')" />
                        </div>
                    </div>
                    <x-form.input name="note" :label="__('Note')" :value="$txn->note" maxlength="500" :placeholder="$kind === 'charge' ? __('e.g. Late payment charge for May EMI') : null" />

                    @if ($txn->exists)
                        <x-form.reason :placeholder="__('e.g. Bank statement shows a different split')" />
                    @endif

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">{{ $loan->lender_name }} · {{ $loan->loan_no }}</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-7">{{ __('Instalments paid') }}</dt><dd class="col-5 text-end">{{ $state['paid_count'] }} / {{ $state['total'] }}</dd>
                    <dt class="col-7">{{ __('Outstanding principal') }}</dt><dd class="col-5 text-end">{{ inr($state['outstanding']) }}</dd>
                    <dt class="col-7">{{ __('Still due (all instalments)') }}</dt><dd class="col-5 text-end">{{ inr($state['remaining_due']) }}</dd>
                    @if ($next = $state['next'])
                        <dt class="col-7 border-top pt-2">{{ __('Next: instalment :n on :date', ['n' => $next->installment_no ?: 'BPI', 'date' => fmt_date($next->due_date)]) }}</dt>
                        <dd class="col-5 text-end border-top pt-2 fw-semibold">{{ inr($next->balance_due) }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@if ($kind === 'payment')
@push('scripts')
<script>
    $(function () {
        const $i = $('#interest_part'), $p = $('#principal_part');
        let typed = {{ $txn->exists ? 'true' : 'false' }};
        $i.add($p).on('input', () => { typed = true; });
        const fill = function () {
            if (typed) return;
            $.getJSON(@json(route('loans.payments.split', $loan)), { amount: $('#amount').val(), except: @json($txn->id) })
                .done(r => { $i.val(r.interest).trigger('change'); $p.val(r.principal).trigger('change'); });
        };
        $('#amount').on('input', fill);
        fill();
    });
</script>
@endpush
@endif
