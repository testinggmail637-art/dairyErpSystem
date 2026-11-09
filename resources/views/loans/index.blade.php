@extends('layouts.app')

@section('title', __('Loans & overdraft'))

@section('page-actions')
    @can('loans.manage')
        <a href="{{ route('loans.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add loan / OD') }}</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('loans.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#loans-results">
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="{{ __('Lender, loan no. or account no.') }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="type" class="form-label small mb-1">{{ __('Type') }}</label>
                <select id="type" name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\LoanType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    @foreach (\App\Enums\LoanStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('loans.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="loans-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Loan') }}</th>
                    <th>{{ __('Lender') }}</th>
                    <th class="text-end">{{ __('Amount / limit') }}</th>
                    <th class="text-end">{{ __('Rate') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th class="text-end">{{ __('EMI') }}</th>
                    <th class="text-center">{{ __('Paid') }}</th>
                    <th class="text-end">{{ __('Outstanding') }}</th>
                    <th>{{ __('Next due') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loans as $loan)
                    <tr>
                        <td>
                            <a href="{{ route('loans.show', $loan) }}" class="fw-semibold text-nowrap">{{ $loan->loan_no }}</a>
                            <div class="small text-body-secondary">{{ $loan->loan_type->label() }}</div>
                        </td>
                        <td>{{ $loan->lender_name }}@if ($loan->account_no)<div class="small text-body-secondary font-monospace">{{ $loan->account_no }}</div>@endif</td>
                        <td class="text-end"><x-money :value="$loan->principal_amount" /></td>
                        <td class="text-end text-nowrap">{{ $loan->interest_rate }} %</td>
                        <td class="text-nowrap small">{{ $loan->tenure_months ? \App\Support\Format::tenure($loan->tenure_months) : '—' }}</td>
                        <td class="text-end">@if ($loan->emi_amount)<x-money :value="$loan->emi_amount" />@else — @endif</td>
                        @php($s = $summaries[$loan->id] ?? null)
                        <td class="text-center text-nowrap small">@if ($s){{ $s['paid_count'] }} / {{ $s['total'] }}<div class="text-body-secondary">{{ inr($s['total_paid']) }}</div>@else — @endif</td>
                        <td class="text-end">@if ($s)<x-money :value="$s['outstanding']" />@else — @endif</td>
                        <td class="text-nowrap">{{ $s && $s['next_due'] ? fmt_date($s['next_due']) : '—' }}</td>
                        <td><span class="badge {{ $loan->status === \App\Enums\LoanStatus::Active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $loan->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-body-secondary py-5">
                        <i class="bi bi-bank fs-2 d-block mb-2"></i>{{ __('No loans match. Add every loan or overdraft the business has taken.') }}
                    </td></tr>
                @endforelse
            </tbody>
            @if ($loans->total() > 0)
                <tfoot class="table-group-divider">
                    <tr class="fw-semibold">
                        <td colspan="2">{{ __('Borrowed (loans) · limits (OD / CC / KCC / cards)') }}</td>
                        <td class="text-end"><x-money :value="$totals->borrowed ?? 0" /> · <x-money :value="$totals->od_limit ?? 0" /></td>
                        <td colspan="7"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @if ($loans->hasPages())<div class="card-footer">{{ $loans->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Paid = instalments fully paid / all instalments, and the amount paid so far. Outstanding = principal still owed. Overdraft, CC, KCC and card balances come with their own pages.') }}</p>
@endsection
