@extends('layouts.app')

@section('title', $loan->loan_no.' · '.$loan->lender_name)

@php
    $open = $loan->status === \App\Enums\LoanStatus::Active;
    $statusBadge = ['paid' => 'text-bg-success', 'partially_paid' => 'text-bg-warning', 'overdue' => 'text-bg-danger', 'due' => 'text-bg-light border'];
    $statusLabel = ['paid' => __('Paid'), 'partially_paid' => __('Part paid'), 'overdue' => __('Overdue'), 'due' => __('Due')];
@endphp

@section('page-actions')
    <a href="{{ route('loans.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Loans') }}</a>
    @if ($loan->hasSchedule() && $open)
        @can('loan_payments.create')
            <a href="{{ route('loans.payments.create', $loan) }}" class="btn btn-primary btn-sm"><i class="bi bi-cash-coin me-1"></i>{{ __('Record payment') }}</a>
            <a href="{{ route('loans.payments.create', [$loan, 'kind' => 'charge']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt me-1"></i>{{ __('Bank charge') }}</a>
            @if ($loan->repayment_method !== \App\Enums\RepaymentMethod::Bullet)
                <a href="{{ route('loans.prepayment.create', $loan) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-skip-forward me-1"></i>{{ __('Prepayment') }}</a>
            @endif
        @endcan
    @elseif ($loan->isRevolving() && $open)
        @can('loan_payments.create')
            <a href="{{ route('loans.entries.create', [$loan, 'type' => 'od_withdrawal']) }}" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up me-1"></i>{{ __('Withdrawal') }}</a>
            <a href="{{ route('loans.entries.create', [$loan, 'type' => 'od_repayment']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i>{{ __('Repayment') }}</a>
            <a href="{{ route('loans.entries.create', [$loan, 'type' => 'od_interest']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-percent me-1"></i>{{ __('Interest debited') }}</a>
            <a href="{{ route('loans.entries.create', [$loan, 'type' => 'charge']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt me-1"></i>{{ __('Charge / refund') }}</a>
        @endcan
    @endif
    @can('loans.manage')
        @if ($open)
            @if ($loan->hasSchedule() && $loan->repayment_method !== \App\Enums\RepaymentMethod::Bullet)
                <a href="{{ route('loans.rate.edit', $loan) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-percent me-1"></i>{{ __('Change rate') }}</a>
            @endif
            <a href="{{ route('loans.edit', $loan) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
            <x-confirm-form :action="route('loans.close', $loan)" method="POST" reason :label="__('Close')" icon="bi-check2-circle"
                            button-class="btn btn-outline-success btn-sm" :message="__('Close loan :no? It must be fully repaid.', ['no' => $loan->loan_no])" :confirm-button="__('Close')" />
        @elseif (auth()->user()->hasRole(\App\Enums\RoleName::Owner->value))
            <x-confirm-form :action="route('loans.reopen', $loan)" method="POST" reason :label="__('Re-open')" icon="bi-arrow-counterclockwise"
                            button-class="btn btn-outline-warning btn-sm" :message="__('Re-open loan :no?', ['no' => $loan->loan_no])" :confirm-button="__('Re-open')" />
        @endif
        @unless ($locked)
            <x-confirm-form :action="route('loans.destroy', $loan)" reason :label="__('Delete')" :message="__('Delete loan :no?', ['no' => $loan->loan_no])" />
        @endunless
    @endcan
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="small text-body-secondary">{{ match ($loan->family()) { \App\Enums\LoanFamily::Revolving => __('Sanctioned limit'), \App\Enums\LoanFamily::CreditCard => __('Credit limit'), default => __('Loan amount') } }}</div>
        <div class="fs-5 fw-semibold"><x-money :value="$loan->principal_amount" /></div>
        <div class="small text-body-secondary">{{ $loan->interest_rate }} % {{ __('a year') }} (= {{ \App\Support\Money::div($loan->interest_rate, '12') }} % {{ __('a month') }})</div>
    </div></div></div>
    @if ($state)
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ match ($loan->repayment_method) { \App\Enums\RepaymentMethod::InterestOnly => __('Interest each instalment'), \App\Enums\RepaymentMethod::Bullet => __('Pay at maturity'), default => __('EMI') } }}</div>
            <div class="fs-5 fw-semibold"><x-money :value="$loan->emi_amount" /></div>
            <div class="small text-body-secondary">{{ $loan->repayment_method->label() }} · {{ mb_strtolower($loan->installment_frequency->label()) }}@if ($loan->moratorium_months) · {{ __(':m months moratorium', ['m' => $loan->moratorium_months]) }}@endif</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Instalments paid') }}</div>
            <div class="fs-5 fw-semibold">{{ $state['paid_count'] }} / {{ $state['total'] }}</div>
            <div class="small text-body-secondary">
                @if ($next = $state['next'])
                    {{ __('Next: :date · :amount', ['date' => fmt_date($next->due_date), 'amount' => inr($next->balance_due)]) }}
                @else
                    {{ __('All paid') }}
                @endif
            </div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100 border-primary"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Outstanding principal') }}</div>
            <div class="fs-5 fw-semibold"><x-money :value="$state['outstanding']" /></div>
            <div class="small text-body-secondary">{{ __('Paid :total (interest :i · principal :p)', ['total' => inr($state['total_paid']), 'i' => inr($state['interest_paid']), 'p' => inr($state['principal_paid'])]) }}</div>
        </div></div></div>
    @elseif ($revolving)
        <div class="col-6 col-lg-3"><div class="card h-100 border-primary"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Used') }}</div>
            <div class="fs-5 fw-semibold"><x-money :value="$revolving['used']" /></div>
            <div class="progress mt-1" style="height: 6px" role="progressbar" aria-label="{{ __('Used') }}">
                <div class="progress-bar" style="width: {{ \App\Support\Money::isZero($loan->principal_amount) ? 0 : min(100, (int) \App\Support\Money::div(\App\Support\Money::mul($revolving['used'], '100'), $loan->principal_amount)) }}%"></div>
            </div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Available') }}</div>
            <div class="fs-5 fw-semibold"><x-money :value="$revolving['available']" /></div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Bank debits interest') }}</div>
            <div class="fs-5">{{ $loan->interest_debit_cycle?->label() }}</div>
            <div class="small text-body-secondary">{{ __('Renewal') }}: {{ $loan->od_renewal_date ? fmt_date($loan->od_renewal_date) : '—' }}</div>
        </div></div></div>
    @else
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-body-secondary">{{ __('Bill day · due day') }}</div>
            <div class="fs-5">{{ $loan->statement_day }} · {{ $loan->due_day }}</div>
            <div class="small text-body-secondary">{{ __('of every month') }}</div>
        </div></div></div>
    @endif
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="card mb-3">
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

        @if ($state)
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Payments & charges') }}</div>
                @if ($loan->status === \App\Enums\LoanStatus::Closed)
                    <div class="card-body py-2 small text-success border-bottom"><i class="bi bi-check2-circle"></i> {{ __('Closed on :date', ['date' => fmt_date($loan->closed_on)]) }}</div>
                @endif
                <ul class="list-group list-group-flush small">
                    @forelse ($transactions as $t)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <span>{{ fmt_date($t->txn_date) }} · {{ $t->type->label() }}</span>
                                <span class="fw-semibold">{{ inr($t->amount) }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-body-secondary">
                                <span>
                                    @if ($t->type !== \App\Enums\LoanTxnType::Charge)
                                        {{ __('interest :i · principal :p', ['i' => inr($t->interest_part), 'p' => inr($t->principal_part)]) }}
                                    @else
                                        {{ $t->note }}
                                    @endif
                                    · {{ $t->paymentMode?->name }}@if ($t->reference_no) · {{ $t->reference_no }}@endif
                                </span>
                                @if ($loop->first && $transactions->onFirstPage())
                                @can('update', $t->setRelation('loan', $loan))
                                    <a href="{{ route('loan-transactions.edit', $t) }}" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No payments yet.') }}</li>
                    @endforelse
                </ul>
                @if ($transactions->hasPages())<div class="card-footer">{{ $transactions->links() }}</div>@endif
            </div>
            @if ($revisions->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header fw-semibold">{{ __('Re-plans (rate changes & prepayments)') }}</div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($revisions as $r)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span>{{ fmt_date($r->effective_date) }} · {{ $r->kind === 'prepayment' ? __('Prepayment') : __('Rate change') }}</span>
                                    <span>{{ $r->old_rate }} % → {{ $r->new_rate }} % · {{ __('EMI') }} {{ inr($r->old_emi) }} → {{ inr($r->new_emi) }}</span>
                                </div>
                                <div class="text-body-secondary">{{ $r->reason }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>

    <div class="col-12 col-xl-8">
        @if ($revolving)
            @php($e = $revolving['estimate'])
            <div class="card mb-3">
                <div class="card-header fw-semibold">{{ __('Check the bank\'s interest') }}</div>
                <div class="card-body">
                    <form method="GET" action="{{ route('loans.show', $loan) }}" class="row g-2 align-items-end mb-3">
                        <div class="col-5 col-md-4"><x-form.date name="from" :label="__('From')" :value="$revolving['period']['from']" group-class="mb-0" /></div>
                        <div class="col-5 col-md-4"><x-form.date name="to" :label="__('To')" :value="$revolving['period']['to']" group-class="mb-0" /></div>
                        <div class="col-2 col-md-4"><button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Show') }}</button></div>
                    </form>
                    <div class="row text-center g-2">
                        <div class="col-4"><div class="small text-body-secondary">{{ __('Estimate (daily balance)') }}</div><div class="fs-5 fw-semibold">{{ inr($e['estimate']) }}</div></div>
                        <div class="col-4"><div class="small text-body-secondary">{{ __('Bank debited (entries in period)') }}</div><div class="fs-5 fw-semibold">{{ inr($e['bank']) }}</div></div>
                        <div class="col-4"><div class="small text-body-secondary">{{ __('Average used') }}</div><div class="fs-5">{{ inr($e['average']) }}</div><div class="small text-body-secondary">{{ __(':d days', ['d' => $e['days']]) }}</div></div>
                    </div>
                    <p class="form-text mb-0 mt-2">{{ __('Estimate = Σ (used at the end of each day × :rate % ÷ 365). Banks round and may debit a few days later, so small differences are normal; a big one is worth asking the bank about.', ['rate' => $loan->interest_rate]) }}</p>
                </div>
            </div>
            <div class="card">
                <div class="card-header fw-semibold">{{ __('Entries') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Entry') }}</th>
                                <th>{{ __('Details') }}</th>
                                <th class="text-end">{{ __('Taken / charged (+)') }}</th>
                                <th class="text-end">{{ __('Repaid / credited (−)') }}</th>
                                <th class="text-end">{{ __('Used after') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($revolving['ledger'] as $row)
                                @php($type = \App\Enums\LoanTxnType::from($row->type))
                                @php($plus = in_array($type, [\App\Enums\LoanTxnType::OdWithdrawal, \App\Enums\LoanTxnType::OdInterest, \App\Enums\LoanTxnType::Charge], true))
                                <tr>
                                    <td class="text-nowrap">{{ fmt_date($row->txn_date) }}</td>
                                    <td>{{ $type->label() }}</td>
                                    <td class="small">{{ collect([$row->mode, $row->reference_no, $row->note])->filter()->implode(' · ') }}</td>
                                    <td class="text-end">{{ $plus ? inr($row->amount) : '' }}</td>
                                    <td class="text-end">{{ $plus ? '' : inr($row->amount) }}</td>
                                    <td class="text-end fw-semibold">{{ inr($row->balance) }}</td>
                                    <td class="text-end">
                                        @if ($loop->first && $revolving['ledger']->onFirstPage() && $open && $revolving['latest']?->id === $row->id)
                                            @can('update', $revolving['latest'])
                                                <a href="{{ route('loan-transactions.entry.edit', $row->id) }}" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No entries yet. Record withdrawals, repayments and the interest the bank debits.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($revolving['ledger']->hasPages())<div class="card-footer">{{ $revolving['ledger']->links() }}</div>@endif
            </div>
        @elseif (! $state)
            <div class="card"><div class="card-body text-body-secondary">{{ __('A credit card has no schedule. Purchases, bill payments, interest and fees are recorded here once card entries are added (Task 3A.7).') }}</div></div>
        @else
            <div class="card">
                <div class="card-header fw-semibold d-flex justify-content-between">
                    <span>{{ __('Repayment schedule') }}</span>
                    <span class="small text-body-secondary">{{ __('Total interest :i · total to pay :t', ['i' => inr(\App\Support\Money::sum($state['rows']->pluck('interest_part'))), 't' => inr(\App\Support\Money::sum($state['rows']->pluck('emi_amount')))]) }}</span>
                </div>
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
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($state['rows'] as $i)
                                <tr @class(['table-danger' => $i->status === 'overdue'])>
                                    <td class="text-nowrap">{{ $i->installment_no ?: __('BPI') }}@if ($i->installment_no === 0)<span class="small text-body-secondary d-block">{{ __('broken period') }}</span>@endif</td>
                                    <td class="text-nowrap">{{ fmt_date($i->due_date) }}</td>
                                    <td class="text-end">{{ inr($i->opening_principal) }}</td>
                                    <td class="text-end fw-semibold">{{ inr($i->emi_amount) }}</td>
                                    <td class="text-end">{{ inr($i->principal_part) }}</td>
                                    <td class="text-end">{{ inr($i->interest_part) }}</td>
                                    <td class="text-end">{{ inr($i->closing_principal) }}</td>
                                    <td class="text-nowrap">
                                        <span class="badge {{ $statusBadge[$i->status] }}">{{ $statusLabel[$i->status] }}</span>
                                        @if ($i->status === 'partially_paid' || ($i->status === 'overdue' && ! \App\Support\Money::isZero(\App\Support\Money::add($i->paid_interest, $i->paid_principal))))
                                            <span class="small text-body-secondary d-block">{{ __(':due left', ['due' => inr($i->balance_due)]) }}</span>
                                        @endif
                                    </td>
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
