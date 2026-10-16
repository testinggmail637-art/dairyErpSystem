@extends('layouts.app')

@section('title', __('Expense'))

@section('page-actions')
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back to expenses') }}</a>
    @can('update', $expense)
        <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
    @endcan
    @can('delete', $expense)
        <x-confirm-form :action="route('expenses.destroy', $expense)" :label="__('Delete')" reason
                        :message="__('Delete this expense of :amount?', ['amount' => inr($expense->amount)])" />
    @endcan
@endsection

@section('content')
@if ($lock = \App\Policies\ExpensePolicy::lockedBecause($expense))
    <div class="alert alert-info"><i class="bi bi-lock-fill me-1"></i>{{ $lock }}</div>
@elseif (! auth()->user()->can('update', $expense) && ! auth()->user()->can('expenses.edit'))
    <div class="alert alert-secondary small"><i class="bi bi-clock-history me-1"></i>{{ __('You can only change your own entries, on the day you made them.') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-body-secondary small">{{ fmt_date($expense->expense_date) }}</div>
                        <div class="fs-3 fw-semibold"><x-money :value="$expense->amount" /></div>
                    </div>
                    @if ($expense->is_credit)
                        <span class="badge text-bg-warning fs-6">{{ __('Credit (unpaid)') }}</span>
                    @else
                        <span class="badge text-bg-success fs-6">{{ __('Paid') }}</span>
                    @endif
                </div>

                <dl class="row mb-0">
                    <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Category') }}</dt>
                    <dd class="col-7 col-md-8">{{ $expense->category?->name }}@if ($expense->subcategory) › {{ $expense->subcategory->name }}@endif</dd>

                    <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Section') }}</dt>
                    <dd class="col-7 col-md-8">{{ $expense->section?->name }}</dd>

                    <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ $expense->vendor ? __('Vendor') : __('Paid to') }}</dt>
                    <dd class="col-7 col-md-8">{{ $expense->payee() ?? '—' }}</dd>

                    @unless ($expense->is_credit)
                        <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Payment mode') }}</dt>
                        <dd class="col-7 col-md-8">{{ $expense->paymentMode?->name ?? '—' }}</dd>
                    @endunless

                    @if ($expense->quantity)
                        <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Quantity') }}</dt>
                        <dd class="col-7 col-md-8">{{ fmt_qty($expense->quantity) }} {{ $expense->unit?->label() }}</dd>
                    @endif

                    @if ($expense->note)
                        <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Note') }}</dt>
                        <dd class="col-7 col-md-8" style="white-space: pre-line">{{ $expense->note }}</dd>
                    @endif

                    <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Entered by') }}</dt>
                    <dd class="col-7 col-md-8">{{ $expense->creator?->name ?? '—' }} · {{ fmt_datetime($expense->created_at) }}</dd>

                    @if ($expense->updated_by && $expense->updated_at?->ne($expense->created_at))
                        <dt class="col-5 col-md-4 fw-normal text-body-secondary">{{ __('Last changed by') }}</dt>
                        <dd class="col-7 col-md-8 mb-0">{{ $expense->updater?->name ?? '—' }} · {{ fmt_datetime($expense->updated_at) }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-paperclip me-1"></i>{{ __('Receipt / bill') }}</div>
            <div class="card-body">
                @if ($expense->receipt_path)
                    @if (str_ends_with(strtolower($expense->receipt_path), '.pdf'))
                        <a href="{{ route('expenses.receipt', $expense) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Open PDF receipt') }}
                        </a>
                    @else
                        <a href="{{ route('expenses.receipt', $expense) }}" target="_blank" rel="noopener">
                            <img src="{{ route('expenses.receipt', $expense) }}" alt="{{ __('Receipt') }}" class="img-fluid rounded border">
                        </a>
                    @endif
                @else
                    <p class="text-body-secondary mb-0">{{ __('No receipt attached.') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
