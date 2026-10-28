@extends('layouts.app')

@section('title', $table->title)

@php($exportQuery = array_filter(\Illuminate\Support\Arr::only($filters, [
    ...match ($filterType) { 'month' => ['month'], 'date' => ['date'], 'none' => [], default => ['from', 'to'] },
    'milk_type',
])))

@section('page-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('All reports') }}</a>
@endsection

@section('content')
<div class="card">
    @if ($filterType !== 'none')
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route($routeName, $report) }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#report-results">
                @if ($filterType === 'month')
                    <div class="col-6 col-md-3">
                        <x-form.input name="month" type="month" :label="__('Month')" :value="$filters['month']" :max="now()->format('Y-m')" group-class="mb-0" class="form-control-sm" />
                    </div>
                @elseif ($filterType === 'date')
                    <div class="col-6 col-md-3">
                        <x-form.date name="date" :label="__('Date')" :value="$filters['date']" max="today" group-class="mb-0" class="form-control-sm" />
                    </div>
                @else
                    <div class="col-6 col-md-3">
                        <x-form.date name="from" :label="__('From')" :value="$filters['from']" max="today" group-class="mb-0" class="form-control-sm" />
                    </div>
                    <div class="col-6 col-md-3">
                        <x-form.date name="to" :label="__('To')" :value="$filters['to']" max="today" group-class="mb-0" class="form-control-sm" />
                    </div>
                @endif
                @if (array_key_exists('milk_type', $filters))
                    <div class="col-6 col-md-3">
                        <x-form.select name="milk_type" :label="__('Milk type')" :options="\App\Enums\MilkType::options()" :selected="$filters['milk_type']"
                                       :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
                    </div>
                @endif
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Show') }}</button>
                </div>
            </form>
            @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
        </div>
    @endif

    <div id="report-results" data-ajax-results>
    @fragment('results')
    <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom app-totals">
        <span class="fw-semibold">{{ $table->period }}</span>
        <span class="small text-body-secondary">· {{ trans_choice(':count row|:count rows', count($table->rows)) }}</span>
        <div class="ms-auto d-flex gap-2">
            <a href="{{ route($routeName, [$report, ...$exportQuery, 'format' => 'excel']) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel') }}</a>
            <a href="{{ route($routeName, [$report, ...$exportQuery, 'format' => 'pdf']) }}" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('PDF') }}</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    @foreach ($table->columns as $i => $column)
                        <th class="{{ $table->align($i) }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($table->rows as $row)
                    <tr @class(['fw-semibold' => $table->isStrong($loop->index)])>
                        @foreach ($row as $i => $value)
                            <td class="{{ $table->align($i) }}">{{ $table->display($i, $value) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($table->columns) }}" class="text-center text-body-secondary py-5">
                        <i class="bi bi-bar-chart fs-2 d-block mb-2"></i>{{ $table->emptyText ?? __('No data in this period.') }}
                    </td></tr>
                @endforelse
            </tbody>
            @if ($table->totals && count($table->rows) > 0)
                <tfoot>
                    <tr class="fw-bold table-light">
                        @foreach ($table->totals as $i => $value)
                            <td class="{{ $table->align($i) }}">{{ $table->display($i, $value) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @foreach ($table->notes as $note)
        <div class="px-3 py-2 small text-body-secondary border-top">{{ $note }}</div>
    @endforeach
    @endfragment
    </div>
</div>
@endsection
