@extends('layouts.app')

@section('title', $loan->exists ? __('Edit :no', ['no' => $loan->loan_no]) : __('Add loan / overdraft'))

@section('page-actions')
    <a href="{{ $loan->exists ? route('loans.show', $loan) : route('loans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}</a>
@endsection

@php
    $months = (int) old('tenure_months', $loan->tenure_months);
@endphp

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        @if ($locked)
            <div class="alert alert-info">{{ __('This loan already has payments, so its amount, rate, period and dates are fixed. Use "Change interest rate" or "Prepayment" on the loan page instead. You can still change the lender details and notes.') }}</div>
        @endif
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $loan->exists ? route('loans.update', $loan) : route('loans.store') }}">
                    @csrf
                    @if ($loan->exists) @method('PUT') @endif

                    <fieldset @disabled($locked)>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <x-form.select name="loan_type" :label="__('Type')" :options="\App\Enums\LoanType::options()" :selected="$loan->loan_type?->value" required />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.money name="principal_amount" :label="__('Loan amount')" :value="$loan->principal_amount" required
                                              :help="__('For an overdraft: the sanctioned limit')" />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.input name="interest_rate" :label="__('Interest % / year')" :value="$loan->interest_rate" inputmode="decimal" suffix="%" required />
                            </div>
                        </div>

                        <div class="row g-3" data-schedule-fields>
                            <div class="col-12 col-md-6">
                                <x-form.select name="repayment_method" :label="__('Repayment')" :options="\App\Enums\RepaymentMethod::options()"
                                               :selected="$loan->repayment_method?->value ?? \App\Enums\RepaymentMethod::EmiReducing->value" />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.input name="tenure_years" type="number" :label="__('Years')" :value="old('tenure_years', intdiv($months, 12) ?: '')" min="0" max="40" />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.input name="tenure_extra_months" type="number" :label="__('+ Months')" :value="old('tenure_extra_months', $months % 12 ?: '')" min="0" max="11" />
                            </div>
                            @error('tenure_months')<div class="col-12 text-danger small mt-0">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-6 col-md-4">
                                <x-form.date name="start_date" :label="__('Loan start date')" :value="$loan->start_date?->toDateString()" max="today" required />
                            </div>
                            <div class="col-6 col-md-4" data-schedule-fields>
                                <x-form.date name="first_due_date" :label="__('First EMI date')" :value="$loan->first_due_date?->toDateString()" />
                            </div>
                            <div class="col-12 col-md-4">
                                <x-form.money name="processing_fee" :label="__('Processing fee')" :value="$loan->processing_fee"
                                              :help="__('Saved as an expense (Finance → Bank Charges)')" />
                            </div>
                        </div>
                    </fieldset>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="lender_name" :label="__('Lender (bank / person)')" :value="$loan->lender_name" maxlength="150" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="lender_phone" type="tel" :label="__('Lender phone')" :value="$loan->lender_phone" maxlength="15" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="account_no" :label="__('Loan / OD account no.')" :value="$loan->account_no" maxlength="50" />
                        </div>
                    </div>

                    <div class="row g-3" data-od-fields>
                        <div class="col-12 col-md-4">
                            <x-form.date name="od_renewal_date" :label="__('OD renewal date')" :value="$loan->od_renewal_date?->toDateString()" />
                        </div>
                    </div>

                    <x-form.textarea name="notes" :label="__('Notes')" :value="$loan->notes" rows="2" maxlength="2000" />

                    @if ($loan->exists)
                        <x-form.reason :placeholder="__('e.g. Wrong account number entered')" />
                    @endif

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ $loan->exists ? route('loans.show', $loan) : route('loans.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        const toggle = function () {
            const od = $('#loan_type').val() === 'overdraft';
            $('[data-schedule-fields]').toggleClass('d-none', od);
            $('[data-od-fields]').toggleClass('d-none', !od);
        };
        $('#loan_type').on('change', toggle);
        toggle();
    });
</script>
@endpush
