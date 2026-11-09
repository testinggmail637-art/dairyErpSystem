@extends('layouts.app')

@section('title', __('Change interest rate').' · '.$loan->loan_no)

@section('page-actions')
    <a href="{{ route('loans.show', $loan) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to loan') }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <p class="small text-body-secondary">{{ __('Current rate :rate % a year, EMI :emi. Unpaid instalments due on or after the date below are re-planned; paid ones never change.', ['rate' => $loan->interest_rate, 'emi' => inr($loan->emi_amount)]) }}</p>
                <form method="POST" action="{{ route('loans.rate.update', $loan) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-6"><x-form.date name="effective_date" :label="__('New rate from')" :value="$state['next']?->due_date?->toDateString()" required /></div>
                        <div class="col-6">
                            <label for="new_rate" class="form-label">{{ __('New interest') }} <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" id="new_rate" name="new_rate" inputmode="decimal" required value="{{ old('new_rate') }}" class="form-control @error('new_rate') is-invalid @enderror">
                                <select name="rate_basis" class="form-select" style="max-width: 7.5rem" aria-label="{{ __('Rate per') }}">
                                    <option value="year">{{ __('% / year') }}</option>
                                    <option value="month" @selected(old('rate_basis') === 'month')>{{ __('% / month') }}</option>
                                </select>
                                @error('new_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="my-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="rc-emi" value="reduce_emi" @checked(old('mode', 'reduce_emi') === 'reduce_emi')>
                            <label class="form-check-label" for="rc-emi">{{ __('Change the EMI, keep the number of instalments') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="rc-tenure" value="reduce_tenure" @checked(old('mode') === 'reduce_tenure')
                                   @disabled($loan->repayment_method !== \App\Enums\RepaymentMethod::EmiReducing)>
                            <label class="form-check-label" for="rc-tenure">{{ __('Keep the EMI, change the number of instalments') }}</label>
                        </div>
                    </div>
                    <x-form.reason :label="__('Reason')" :placeholder="__('e.g. Bank letter: repo rate change from 01-07-2026')" />
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Change rate') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
