@extends('layouts.app')

@section('title', $loan->loan_no.' · '.$loan->lender_name)

@section('page-actions')
    <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Loans') }}</a>
    @can('loans.manage')
        @if ($loan->status === \App\Enums\LoanStatus::Active)
            <a href="{{ route('loans.edit', $loan) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
        @endif
        @unless ($locked)
            <x-confirm-form :action="route('loans.destroy', $loan)" reason :label="__('Delete')" :message="__('Delete loan :no?', ['no' => $loan->loan_no])" />
        @endunless
    @endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ $loan->isOverdraft() ? __('Sanctioned limit') : __('Loan amount') }}</div>
        <div class="fs-5 fw-semibold"><x-money :value="$loan->principal_amount" /></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ __('Interest') }}</div>
        <div class="fs-5">{{ $loan->interest_rate }} % {{ __('a year') }}</div>
        @if ($loan->repayment_method)<div class="small text-body-secondary">{{ $loan->repayment_method->label() }}</div>@endif
    </div></div></div>
    @unless ($loan->isOverdraft())
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ $loan->repayment_method === \App\Enums\RepaymentMethod::InterestOnly ? __('Monthly interest') : __('EMI') }}</div>
            <div class="fs-5 fw-semibold"><x-money :value="$loan->emi_amount" /></div>
            <div class="small text-body-secondary">{{ __(':n instalments from :date', ['n' => $loan->tenure_months, 'date' => fmt_date($loan->first_due_date)]) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Total interest (schedule)') }}</div>
            <div class="fs-5"><x-money :value="\App\Support\Money::sum($installments->pluck('interest_part'))" /></div>
            <div class="small text-body-secondary">{{ __('Total to pay') }} <x-money :value="\App\Support\Money::sum($installments->pluck('emi_amount'))" /></div>
        </div></div></div>
    @else
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Renewal date') }}</div>
            <div class="fs-5">{{ $loan->od_renewal_date ? fmt_date($loan->od_renewal_date) : '—' }}</div>
        </div></div></div>
    @endunless
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Details') }}</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7">{{ $loan->loan_type->label() }}</dd>
                    <dt class="col-5">{{ __('Lender') }}</dt><dd class="col-7">{{ $loan->lender_name }}@if ($loan->lender_phone)<br>{{ $loan->lender_phone }}@endif</dd>
                    <dt class="col-5">{{ __('Account no.') }}</dt><dd class="col-7 font-monospace">{{ $loan->account_no ?: '—' }}</dd>
                    <dt class="col-5">{{ __('Start date') }}</dt><dd class="col-7">{{ fmt_date($loan->start_date) }}</dd>
                    <dt class="col-5">{{ __('Processing fee') }}</dt>
                    <dd class="col-7"><x-money :value="$loan->processing_fee" />@if ($feeExpenseId) <span class="small text-body-secondary">({{ __('in expenses') }})</span>@endif</dd>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7">{{ $loan->status->label() }}</dd>
                    @if ($loan->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $loan->notes }}</dd>@endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        @if ($loan->isOverdraft())
            <div class="card"><div class="card-body text-body-secondary">{{ __('An overdraft has no EMI schedule. Withdrawals, repayments and bank interest are recorded on this page once overdraft entries are added (next task).') }}</div></div>
        @else
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Repayment schedule') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Due date') }}</th>
                                <th class="text-end">{{ __('Opening') }}</th>
                                <th class="text-end">{{ __('EMI') }}</th>
                                <th class="text-end">{{ __('Principal') }}</th>
                                <th class="text-end">{{ __('Interest') }}</th>
                                <th class="text-end">{{ __('Closing') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($installments as $i)
                                <tr>
                                    <td>{{ $i->installment_no }}</td>
                                    <td class="text-nowrap">{{ fmt_date($i->due_date) }}</td>
                                    <td class="text-end">{{ inr($i->opening_principal) }}</td>
                                    <td class="text-end fw-semibold">{{ inr($i->emi_amount) }}</td>
                                    <td class="text-end">{{ inr($i->principal_part) }}</td>
                                    <td class="text-end">{{ inr($i->interest_part) }}</td>
                                    <td class="text-end">{{ inr($i->closing_principal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
