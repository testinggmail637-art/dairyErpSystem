@extends('layouts.app')

@section('title', ($txn->exists ? __('Edit entry') : __('New entry')).' · '.$loan->loan_no)

@section('page-actions')
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to account') }}</a>
    @if ($txn->exists)
        @can('delete', $txn)
            <x-confirm-form :action="route('loan-transactions.destroy', $txn)" reason :label="__('Delete')"
                            :message="__('Delete this entry of :amount?', ['amount' => inr($txn->amount)])" />
        @endcan
    @endif
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $txn->exists ? route('loan-transactions.entry.update', $txn) : route('loans.entries.store', $loan) }}">
                    @csrf
                    @if ($txn->exists) @method('PUT') @else <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid> @endif

                    <x-form.select name="type" :label="__('Entry')" :options="$types" :selected="$txn->type?->value" required />
                    <div class="row g-3">
                        <div class="col-6"><x-form.date name="txn_date" :label="__('Date (as on the bank statement)')" :value="$txn->txn_date?->toDateString()" max="today" required /></div>
                        <div class="col-6"><x-form.money name="amount" :label="__('Amount')" :value="$txn->amount" required /></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6"><x-form.select name="payment_mode_id" :label="__('Paid / received by')" :options="$paymentModes" :selected="$txn->payment_mode_id" :placeholder="__('—')"
                                                          :help="__('Needed for a repayment. Interest and charges default to Bank Transfer.')" /></div>
                        <div class="col-6"><x-form.input name="reference_no" :label="__('Reference no.')" :value="$txn->reference_no" maxlength="50" /></div>
                    </div>
                    <x-form.input name="note" :label="__('Note')" :value="$txn->note" maxlength="500" :placeholder="__('e.g. Interest for October from the statement')" />

                    @if ($txn->exists)
                        <x-form.reason :placeholder="__('e.g. Amount differs from the bank statement')" />
                    @endif

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-7">{{ __('Limit') }}</dt><dd class="col-5 text-end">{{ inr($loan->principal_amount) }}</dd>
                <dt class="col-7">{{ __('Used now') }}</dt><dd class="col-5 text-end">{{ inr($used) }}</dd>
                <dt class="col-7">{{ __('Available') }}</dt><dd class="col-5 text-end fw-semibold">{{ inr(\App\Support\Money::sub($loan->principal_amount, $used)) }}</dd>
            </dl>
            <hr>
            <ul class="mb-0 ps-3 text-body-secondary">
                <li>{{ __('Withdrawal: money taken from the account (cannot go over the limit).') }}</li>
                <li>{{ __('Repayment: money put back.') }}</li>
                <li>{{ __('Interest debited: the interest the bank charged (from the statement) — saved as an expense.') }}</li>
                <li>{{ __('Bank charge: renewal, processing or other fee — saved as an expense.') }}</li>
                <li>{{ __('Subvention / refund: interest the bank credited back (e.g. KCC prompt repayment).') }}</li>
            </ul>
        </div></div>
    </div>
</div>
@endsection
