@extends('layouts.app')

@section('title', __('Feed stock'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('feed.transactions.create', ['type' => 'purchase']) }}" class="btn btn-primary btn-sm"><i class="bi bi-cart-plus me-1"></i>{{ __('Purchase') }}</a>
        <a href="{{ route('feed.transactions.create', ['type' => 'consumption']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-basket me-1"></i>{{ __('Consumption') }}</a>
        <a href="{{ route('feed.items.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Feed item') }}</a>
    @endif
@endsection

@section('content')
@include('herd.feed._tabs')
<div class="card">
    <div class="card-body border-bottom small d-flex flex-wrap gap-4 align-items-center">
        <div>{{ __('Stock value') }}: <span class="fw-semibold"><x-money :value="$totalValue" /></span>
            <span class="text-body-secondary">{{ __('(at the average purchase cost of the last 90 days)') }}</span></div>
        @if ($lowCount)<div class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>{{ trans_choice(':count item at or below reorder level|:count items at or below reorder level', $lowCount) }}</div>@endif
        <a href="{{ route('feed.index', $showAll ? [] : ['all' => 1]) }}" class="ms-auto">{{ $showAll ? __('Active items only') : __('Show inactive items too') }}</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Feed') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Reorder at') }}</th>
                    <th class="text-end">{{ __('Avg cost / unit') }}</th><th class="text-end">{{ __('Value') }}</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($item = $row['item'])
                    <tr @class(['table-danger' => $row['low']])>
                        <td>
                            {{ $item->name }}
                            @unless ($item->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless
                            @if ($row['low'])<span class="badge text-bg-danger">{{ __('Low') }}</span>@endif
                        </td>
                        <td class="text-end fw-semibold text-nowrap">{{ fmt_qty($row['stock']) }} {{ $item->unit->label() }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Money::isZero($item->reorder_level) ? '—' : fmt_qty($item->reorder_level) }}</td>
                        <td class="text-end">{{ $row['avg_cost'] !== null ? inr($row['avg_cost']) : '—' }}</td>
                        <td class="text-end">{{ $row['value'] !== null ? inr($row['value']) : '—' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('feed.transactions.index', ['feed_item_id' => $item->id, 'from' => '2000-01-01']) }}" class="btn btn-link btn-sm p-0" title="{{ __('Entries') }}" aria-label="{{ __('Entries') }}"><i class="bi bi-list-ul"></i></a>
                            @if ($canManage)
                                <div class="dropdown d-inline">
                                    <button class="btn btn-link btn-sm p-0 ms-1" data-bs-toggle="dropdown" aria-label="{{ __('More') }}"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        @foreach (['opening', 'purchase', 'consumption', 'adjustment_in', 'adjustment_out'] as $type)
                                            <li><a class="dropdown-item" href="{{ route('feed.transactions.create', ['type' => $type, 'feed_item_id' => $item->id]) }}">{{ \App\Enums\FeedTransactionType::from($type)->label() }}</a></li>
                                        @endforeach
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="{{ route('feed.items.edit', $item) }}">{{ __('Edit item') }}</a></li>
                                    </ul>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">{{ __('No feed items yet. Add one, then enter its opening stock.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
</div>
@endsection
