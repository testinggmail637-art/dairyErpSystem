@extends('layouts.app')

@section('title', __('Prepayment').' · '.$loan->loan_no)

@section('page-actions')
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to loan') }}</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('loans.prepayment.store', $loan) }}">
                    @csrf
                    <input type="hidden" name="client_uuid" value="{{ old('client_uuid') }}" data-client-uuid>
                    <div class="row g-3">
                        <div class="col-6"><x-form.date name="txn_date" :label="__('Date')" :value="today()->toDateString()" max="today" required /></div>
                        <div class="col-6"><x-form.money name="amount" :label="__('Prepayment amount')" required :help="__('Goes fully to principal.')" /></div>
                    </div>

                    <div class="mb-3">
                        <div class="form-label">{{ __('After the prepayment') }}</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode-emi" value="reduce_emi" @checked(old('mode', 'reduce_emi') === 'reduce_emi')>
                            <label class="form-check-label" for="mode-emi">{{ __('Lower EMI, same number of instalments') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode-tenure" value="reduce_tenure" @checked(old('mode') === 'reduce_tenure')
                                   @disabled($loan->repayment_method !== \App\Enums\RepaymentMethod::EmiReducing)>
                            <label class="form-check-label" for="mode-tenure">{{ __('Same EMI, finish earlier (saves more interest)') }}</label>
                        </div>
                        @error('mode')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-6"><x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :placeholder="__('Choose')" required /></div>
                        <div class="col-6"><x-form.input name="reference_no" :label="__('Reference no.')" maxlength="50" /></div>
                    </div>
                    <x-form.input name="note" :label="__('Note')" maxlength="500" />
                    <p class="form-text">{{ __('A foreclosure / part-payment fee charged by the bank is a separate "Bank charge" entry.') }}</p>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save prepayment') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-7">{{ __('Outstanding principal') }}</dt><dd class="col-5 text-end">{{ inr($state['outstanding']) }}</dd>
                <dt class="col-7">{{ __('EMI now') }}</dt><dd class="col-5 text-end">{{ inr($loan->emi_amount) }}</dd>
                <dt class="col-7">{{ __('Instalments left') }}</dt><dd class="col-5 text-end">{{ $state['total'] - $state['paid_count'] }}</dd>
                @if ($state['next'] && $state['next']->status !== 'due')
                    <dd class="col-12 text-warning mt-2">{{ __('Pay the current instalment (:amount) before a prepayment.', ['amount' => inr($state['next']->balance_due)]) }}</dd>
                @endif
            </dl>
        </div></div>
    </div>
</div>
@endsection
