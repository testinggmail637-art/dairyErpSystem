@extends('layouts.app')

@section('title', __('Rate Charts'))

@section('page-actions')
    <a href="{{ route('rate-charts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add rate chart') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('rate-charts.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#charts-results">
            <div class="col-6 col-md-3">
                <label for="milk_type" class="form-label small mb-1">{{ __('Milk') }}</label>
                <select id="milk_type" name="milk_type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\MilkType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['milk_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('rate-charts.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="charts-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Milk') }}</th>
                    <th>{{ __('Method') }}</th>
                    <th>{{ __('Rates') }}</th>
                    <th>{{ __('Valid') }}</th>
                    <th class="text-end">{{ __('Farmers') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charts as $chart)
                    <tr>
                        <td>
                            <a href="{{ route('rate-charts.show', $chart) }}" class="fw-semibold">{{ $chart->name }}</a>
                            @if ($chart->is_default)<span class="badge text-bg-primary ms-1">{{ __('Default') }}</span>@endif
                        </td>
                        <td>{{ $chart->milk_type->label() }}</td>
                        <td>{{ $chart->method->label() }}</td>
                        <td class="small text-nowrap">@include('collection.rate-charts.rates', ['chart' => $chart])</td>
                        <td class="small text-nowrap">{{ fmt_date($chart->effective_from) }} – {{ $chart->effective_to ? fmt_date($chart->effective_to) : __('open') }}</td>
                        <td class="text-end">{{ $farmerCounts[$chart->id] ?? 0 }}</td>
                        <td>
                            @if ($chart->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-table fs-2 d-block mb-2"></i>
                            {{ __('No rate charts yet. Add one default chart for cow milk and one for buffalo milk.') }}
                            <div class="mt-2"><a href="{{ route('rate-charts.create') }}" class="btn btn-primary btn-sm">{{ __('Add rate chart') }}</a></div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($charts->hasPages())<div class="card-footer">{{ $charts->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
