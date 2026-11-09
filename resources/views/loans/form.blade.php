@extends('layouts.app')

@section('title', $loan->exists ? __('Edit :no', ['no' => $loan->loan_no]) : __('Add loan / overdraft / credit card'))

@section('page-actions')
    <a href="{{ $loan->exists ? route('loans.show', $loan) : route('loans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}</a>
@endsection

@php
    $months = (int) old('tenure_months', $loan->tenure_months);
    $types = collect(\App\Enums\LoanFamily::cases())->mapWithKeys(fn ($f) => [$f->label() => collect(\App\Enums\LoanType::valuesOf($f))
        ->mapWithKeys(fn ($v) => [$v => \App\Enums\LoanType::from($v)->label()])->all()])->all();
    $familyOf = collect(\App\Enums\LoanType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->family()->value])->all();
    $selectedType = old('loan_type', $loan->loan_type?->value);
@endphp

@section('content')
<div class="row">
    <div class="col-12 col-lg-10 col-xl-8">
        @if ($locked)
            <div class="alert alert-info">{{ __('This loan already has entries, so its amount, rate, period and dates are fixed. Use "Change interest rate" or "Prepayment" on the loan page instead. You can still change the lender details and notes.') }}</div>
        @endif
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $loan->exists ? route('loans.update', $loan) : route('loans.store') }}" id="loan-form">
                    @csrf
                    @if ($loan->exists) @method('PUT') @endif

                    <fieldset @disabled($locked)>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="loan_type" class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
                                <select id="loan_type" name="loan_type" class="form-select @error('loan_type') is-invalid @enderror" required>
                                    @foreach ($types as $group => $options)
                                        <optgroup label="{{ $group }}">
                                            @foreach ($options as $value => $label)
                                                <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('loan_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.money name="principal_amount" :label="__('Loan amount')" :value="$loan->principal_amount" required
                                              data-label-instalment="{{ __('Loan amount') }}" data-label-revolving="{{ __('Sanctioned limit') }}" data-label-credit_card="{{ __('Credit limit') }}" />
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="interest_rate" class="form-label">{{ __('Interest') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" id="interest_rate" name="interest_rate" inputmode="decimal" required
                                           class="form-control @error('interest_rate') is-invalid @enderror" value="{{ old('interest_rate', $loan->interest_rate) }}">
                                    <select name="rate_basis" class="form-select" style="max-width: 7.5rem" aria-label="{{ __('Rate per') }}">
                                        <option value="year" @selected(old('rate_basis', 'year') === 'year')>{{ __('% / year') }}</option>
                                        <option value="month" @selected(old('rate_basis') === 'month')>{{ __('% / month') }}</option>
                                    </select>
                                    @error('interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">{{ __('"₹2 per ₹100 a month" = 2 % / month') }}</div>
                            </div>
                        </div>

                        {{-- Instalment loans --}}
                        <div data-family="instalment">
                            <div class="row g-3 mt-0">
                                <div class="col-12 col-md-6">
                                    <x-form.select name="repayment_method" :label="__('Repayment')" :options="\App\Enums\RepaymentMethod::options()"
                                                   :selected="$loan->repayment_method?->value ?? \App\Enums\RepaymentMethod::EmiReducing->value" />
                                </div>
                                <div class="col-12 col-md-6" data-scheduled>
                                    <x-form.select name="installment_frequency" :label="__('Instalment every')" :options="\App\Enums\InstallmentFrequency::options()"
                                                   :selected="$loan->installment_frequency?->value ?? 'monthly'" />
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <x-form.input name="tenure_years" type="number" :label="__('Period: years')" :value="old('tenure_years', intdiv($months, 12) ?: '')" min="0" max="40" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <x-form.input name="tenure_extra_months" type="number" :label="__('+ months')" :value="old('tenure_extra_months', $months % 12 ?: '')" min="0" max="11" />
                                </div>
                                <div class="col-6 col-md-3" data-scheduled>
                                    <x-form.input name="moratorium_months" type="number" :label="__('Moratorium (months)')" :value="$loan->moratorium_months ?: ''" min="0" max="479"
                                                  :help="__('No principal for these months')" />
                                </div>
                                <div class="col-6 col-md-3" data-scheduled data-moratorium>
                                    <x-form.select name="moratorium_interest" :label="__('Interest in moratorium')" :options="\App\Enums\MoratoriumInterest::options()"
                                                   :selected="$loan->moratorium_interest?->value" :placeholder="__('Choose')" />
                                </div>
                                @error('tenure_months')<div class="col-12 text-danger small mt-0">{{ $message }}</div>@enderror
                            </div>
                            <div class="row g-3" data-scheduled>
                                <div class="col-6 col-md-4">
                                    <x-form.date name="first_due_date" :label="__('First instalment date')" :value="$loan->first_due_date?->toDateString()"
                                                 :help="__('With a moratorium added to the loan: the first EMI')" />
                                </div>
                                <div class="col-6 col-md-4">
                                    <x-form.money name="broken_period_interest" :label="__('Broken-period interest')" :value="$loan->exists ? $loan->broken_period_interest : null"
                                                  :help="__('Empty = calculated (days before the first period). Copy it from the sanction letter if it differs.')" />
                                </div>
                            </div>
                        </div>

                        {{-- Overdraft / cash credit / KCC --}}
                        <div class="row g-3" data-family="revolving">
                            <div class="col-12 col-md-6">
                                <x-form.select name="interest_debit_cycle" :label="__('Bank debits interest')" :options="\App\Enums\InstallmentFrequency::options()"
                                               :selected="$loan->interest_debit_cycle?->value ?? 'monthly'" :help="__('Interest is charged only on the amount used, day by day.')" />
                            </div>
                        </div>

                        {{-- Credit card --}}
                        <div class="row g-3" data-family="credit_card">
                            <div class="col-6 col-md-3">
                                <x-form.input name="statement_day" type="number" :label="__('Bill day')" :value="$loan->statement_day" min="1" max="31" />
                            </div>
                            <div class="col-6 col-md-3">
                                <x-form.input name="due_day" type="number" :label="__('Due day')" :value="$loan->due_day" min="1" max="31" />
                            </div>
                            <div class="col-12 col-md-6 form-text pt-md-4">{{ __('No interest if the full bill is paid by the due day. Card rates are usually 3–3.75 % a month.') }}</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-6 col-md-4">
                                <x-form.date name="start_date" :label="__('Start / issue date')" :value="$loan->start_date?->toDateString()" max="today" required />
                            </div>
                            <div class="col-6 col-md-4">
                                <x-form.money name="processing_fee" :label="__('Processing fee (incl. GST)')" :value="$loan->processing_fee"
                                              :help="__('Saved as an expense (Finance → Bank Charges)')" />
                            </div>
                        </div>
                    </fieldset>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <x-form.input name="lender_name" :label="__('Lender (bank / card issuer / person)')" :value="$loan->lender_name" maxlength="150" required />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="lender_phone" type="tel" :label="__('Lender phone')" :value="$loan->lender_phone" maxlength="15" />
                        </div>
                        <div class="col-6 col-md-3">
                            <x-form.input name="account_no" :label="__('Account / card no.')" :value="$loan->account_no" maxlength="50" :help="__('Last 4 digits are enough for a card')" />
                        </div>
                    </div>

                    <div class="row g-3" data-family="revolving">
                        <div class="col-12 col-md-4">
                            <x-form.date name="od_renewal_date" :label="__('Limit renewal date')" :value="$loan->od_renewal_date?->toDateString()" />
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
        const familyOf = @json($familyOf);
        const $form = $('#loan-form');
        const toggle = function () {
            const family = familyOf[$('#loan_type').val()] || 'instalment';
            const scheduled = family === 'instalment' && $('#repayment_method').val() !== 'bullet';

            $form.find('[data-family]').each(function () { $(this).toggleClass('d-none', $(this).data('family') !== family); });
            $form.find('[data-scheduled]').toggleClass('d-none', !scheduled);
            $form.find('[data-moratorium]').toggleClass('d-none', !scheduled || !(parseInt($('#moratorium_months').val(), 10) > 0));

            const $amount = $('#principal_amount');
            $('label[for="principal_amount"]').contents().first()[0].textContent = $amount.data('label-' + family) + ' ';
        };
        $('#loan_type, #repayment_method').on('change', toggle);
        $('#moratorium_months').on('input', toggle);
        toggle();
    });
</script>
@endpush
