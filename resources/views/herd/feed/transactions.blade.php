@extends('layouts.app')

@section('title', __('Feed entries'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('feed.transactions.create', ['type' => 'purchase']) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New entry') }}</a>
    @endif
@endsection

@section('content')
@include('herd.feed._tabs')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('feed.transactions.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#feed-list">
            <div class="col-6 col-md-2">
                <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-2">
                <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.select name="feed_item_id" id="filter_item" :label="__('Feed')" :options="$items" :selected="$filters['feed_item_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3">
                <x-form.select name="type" id="filter_type" :label="__('Type')" :options="$types" :selected="$filters['type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('feed.transactions.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="feed-list" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('Feed') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Quantity') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th><th>{{ __('Details') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($e->transaction_date) }}</td>
                        <td>{{ $e->item->name }}</td>
                        <td>{{ $e->type->label() }}</td>
                        <td @class(['text-end text-nowrap', 'text-success' => $e->type->isIn(), 'text-danger' => ! $e->type->isIn()])>
                            {{ $e->type->isIn() ? '+' : '−' }}{{ fmt_qty($e->quantity) }} {{ $e->item->unit->label() }}</td>
                        <td class="text-end text-nowrap">
                            @if ($e->amount !== null){{ inr($e->amount) }}@endif
                            @if ($e->expense_id)
                                <a href="{{ route('expenses.show', $e->expense_id) }}" class="ms-1" title="{{ __('Expense') }}" aria-label="{{ __('Expense') }}"><i class="bi bi-receipt"></i></a>
                            @endif
                        </td>
                        <td class="small">
                            {{ collect([
                                $e->vendor?->name,
                                $e->type === \App\Enums\FeedTransactionType::Purchase ? ($e->is_credit ? __('Credit') : $e->paymentMode?->name) : null,
                                $e->animal?->label(),
                                $e->notes,
                            ])->filter()->implode(' · ') }}
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($canManage)
                                <a href="{{ route('feed.transactions.edit', $e) }}" class="btn btn-link btn-sm p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-form :action="route('feed.transactions.destroy', $e)" reason
                                                :message="$e->expense_id ? __('Delete this purchase and its expense of :amount?', ['amount' => inr($e->amount)]) : __('Delete this feed entry?')" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">{{ __('No feed entries found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())<div class="card-footer">{{ $entries->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
