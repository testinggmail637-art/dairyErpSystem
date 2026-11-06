@extends('layouts.app')

@section('title', __('Delivery grid'))

@section('page-actions')
    <a href="{{ route('deliveries.dispatch.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-truck me-1"></i>{{ __('One-off / B2B dispatch') }}</a>
    <a href="{{ route('deliveries.index', ['date' => $filters['date']]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Summary') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('deliveries.grid') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#grid-results">
            <div class="col-6 col-md-2">
                <x-form.date name="date" :label="__('Date')" :value="$filters['date']" group-class="mb-0" />
            </div>
            <div class="col-6 col-md-2">
                <label for="shift" class="form-label small mb-1">{{ __('Shift') }}</label>
                <select id="shift" name="shift" class="form-select form-select-sm">
                    <option value="">{{ __('Both') }}</option>
                    @foreach (\App\Enums\MilkShift::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['shift'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="route_id" class="form-label small mb-1">{{ __('Route') }}</label>
                <select id="route_id" name="route_id" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="none" @selected($filters['route_id'] === 'none')>{{ __('No route') }}</option>
                    @foreach ($routes as $id => $name)
                        <option value="{{ $id }}" @selected($filters['route_id'] === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\DeliveryStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-8 col-md-2">
                <label for="search" class="form-label small mb-1">{{ __('Customer') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm" placeholder="{{ __('Name, code or phone') }}">
            </div>
            <div class="col-4 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('deliveries.grid') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('deliveries.bulk-deliver') }}" id="bulk-form">@csrf</form>

    <div id="grid-results" data-ajax-results>
    @fragment('results')
    <div class="card-body border-bottom d-flex flex-wrap gap-3 align-items-center small">
        <span>{{ __('Rows') }}: <strong>{{ $totals->total }}</strong></span>
        <span>{{ __('Pending') }}: <strong>{{ (int) $totals->pending }}</strong></span>
        <span>{{ __('Planned') }}: <strong>{{ fmt_qty($totals->planned ?? 0) }} L</strong></span>
        <span>{{ __('Delivered') }}: <strong>{{ fmt_qty($totals->delivered ?? 0) }} L</strong></span>
        <span>{{ __('Amount') }}: <strong><x-money :value="$totals->amount ?? 0" /></strong></span>
        <button type="submit" form="bulk-form" class="btn btn-success btn-sm ms-md-auto" data-bulk-submit disabled>
            <i class="bi bi-check2-all me-1"></i>{{ __('Mark ticked delivered') }} (<span data-bulk-count>0</span>)
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th><input type="checkbox" class="form-check-input" data-bulk-all aria-label="{{ __('Tick all pending on this page') }}"></th>
                    <th>{{ __('Customer') }}</th>
                    <th>{{ __('Route') }}</th>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Shift') }}</th>
                    <th class="text-end">{{ __('Planned') }}</th>
                    <th class="text-end">{{ __('Delivered') }}</th>
                    <th class="text-end">{{ __('Rate') }}</th>
                    <th class="text-end">{{ __('Amount') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deliveries as $d)
                    @php($canMark = $d->status === \App\Enums\DeliveryStatus::Pending && ! $d->isBilled() && $d->delivery_date->toDateString() <= $today)
                    <tr>
                        <td>
                            @if ($canMark)
                                <input type="checkbox" class="form-check-input" name="ids[]" value="{{ $d->id }}" form="bulk-form" data-bulk-item aria-label="{{ __('Tick') }}">
                            @endif
                        </td>
                        <td>
                            <span class="fw-semibold">{{ $d->customer->name }}</span>
                            <span class="small text-body-secondary font-monospace">{{ $d->customer->code }}</span>
                        </td>
                        <td class="text-nowrap small">
                            {{ $d->route?->name ?? '—' }}@if ($d->route && $d->customer->route_sequence) #{{ $d->customer->route_sequence }}@endif
                        </td>
                        <td>{{ $d->product->name }}</td>
                        <td>{{ $d->shift->label() }}</td>
                        <td class="text-end">{{ fmt_qty($d->planned_qty) }}</td>
                        <td class="text-end">{{ fmt_qty($d->delivered_qty) }}</td>
                        <td class="text-end"><x-money :value="$d->rate" /></td>
                        <td class="text-end"><x-money :value="$d->amount" /></td>
                        <td>
                            @switch($d->status)
                                @case(\App\Enums\DeliveryStatus::Delivered)
                                    <span class="badge text-bg-success" title="{{ $d->deliveredBy?->name }}">{{ __('Delivered') }}</span>
                                    @break
                                @case(\App\Enums\DeliveryStatus::Skipped)
                                    <span class="badge text-bg-secondary" title="{{ $d->skip_reason }}">{{ __('Skipped') }}</span>
                                    @break
                                @default
                                    <span class="badge text-bg-warning">{{ __('Pending') }}</span>
                            @endswitch
                            @if ($d->isBilled())<i class="bi bi-lock text-body-secondary" title="{{ __('Billed — locked') }}"></i>@endif
                        </td>
                        <td class="text-end">
                            @unless ($d->isBilled() || $d->delivery_date->toDateString() > $today)
                                <a href="{{ route('deliveries.edit', $d) }}" class="btn btn-outline-primary btn-sm" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-body-secondary py-5">
                        <i class="bi bi-truck fs-2 d-block mb-2"></i>{{ __('No deliveries match. Generate the sheet for this date on the Summary page.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($deliveries->hasPages())<div class="card-footer">{{ $deliveries->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Bulk marking uses each row\'s planned litres; for different litres or a skip, use the pencil. Billed rows are locked.') }}</p>
@endsection

@push('scripts')
<script>
    $(function () {
        // Delegated, so it keeps working after the list is reloaded by AJAX.
        const refresh = function () {
            const n = $('[data-bulk-item]:checked').length;
            $('[data-bulk-count]').text(n);
            $('[data-bulk-submit]').prop('disabled', n === 0);
        };
        $(document).on('change', '[data-bulk-all]', function () {
            $('[data-bulk-item]').prop('checked', this.checked);
            refresh();
        });
        $(document).on('change', '[data-bulk-item]', refresh);
    });
</script>
@endpush
