{{-- Dashboard (spec 7.5): cards and charts for the modules built so far; each block is hidden without its permission. --}}
@extends('layouts.app')

@section('title', __('Dashboard'))

@php
    $canMilk = auth()->user()->can('milk_production.view');
    $canStock = auth()->user()->can('milk_stock.view');
    $canHerd = auth()->user()->can('animals.view');
    $canMoney = auth()->user()->can('dashboard.financial') && auth()->user()->can('expenses.view');
    $production = $data['production'];
    $stock = $data['stock'];
    $herd = $data['herd'];
    $alerts = $data['alerts'];
    $expenses = $data['expenses'];
@endphp

@section('page-actions')
    <span class="text-body-secondary small align-self-center"><i class="bi bi-calendar3 me-1"></i>{{ fmt_date(today()) }}</span>
    @can('milk_production.create')
        <a href="{{ route('milk-production.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Milk entry') }}</a>
    @endcan
    @can('expenses.create')
        <a href="{{ route('expenses.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt me-1"></i>{{ __('Add expense') }}</a>
    @endcan
@endsection

@section('content')
    <p class="text-body-secondary mb-3">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</p>

    {{-- KPI cards --}}
    <div class="row g-3 mb-3">
        @if ($canMilk)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('milk-production.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-droplet-half"></i></div>
                        <div class="stat-label">{{ __('Milk today') }}</div>
                        <div class="stat-value">{{ fmt_qty($production['today']['total']) }} <small>L</small></div>
                        <div class="stat-meta">
                            {{ __('Cow') }} {{ fmt_qty($production['today']['cow']) }} · {{ __('Buffalo') }} {{ fmt_qty($production['today']['buffalo']) }}
                            @if ($production['change_pct'] !== null)
                                <span class="stat-change {{ str_starts_with($production['change_pct'], '-') ? 'is-down' : 'is-up' }}">
                                    <i class="bi bi-arrow-{{ str_starts_with($production['change_pct'], '-') ? 'down' : 'up' }}-short"></i>{{ ltrim($production['change_pct'], '-') }}%
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('milk-production.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-calendar2-week"></i></div>
                        <div class="stat-label">{{ __('Milk this month') }}</div>
                        <div class="stat-value">{{ fmt_qty($production['month_total']) }} <small>L</small></div>
                        <div class="stat-meta">{{ __('Yesterday') }} {{ fmt_qty($production['yesterday_total']) }} L</div>
                    </div>
                </a>
            </div>
        @endif
        @if ($canStock)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('milk-stock.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                        <div class="stat-label">{{ __('Milk in stock') }}</div>
                        <div class="stat-value">{{ fmt_qty($stock['total']) }} <small>L</small></div>
                        <div class="stat-meta">{{ __('Wastage today') }} {{ fmt_qty($stock['wastage_today']) }} L</div>
                    </div>
                </a>
            </div>
        @endif
        @if ($canHerd)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('animals.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-heart-pulse"></i></div>
                        <div class="stat-label">{{ __('Active animals') }}</div>
                        <div class="stat-value">{{ $herd['active'] }}</div>
                        <div class="stat-meta">{{ __(':n milking', ['n' => $herd['milking']]) }}</div>
                    </div>
                </a>
            </div>
        @endif
        @if ($canMoney)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('expenses.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-label">{{ __('Expenses today') }}</div>
                        <div class="stat-value">{{ inr($expenses['today']) }}</div>
                        <div class="stat-meta">{{ __('Entered for today') }}</div>
                    </div>
                </a>
            </div>
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('expenses.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon"><i class="bi bi-graph-down-arrow"></i></div>
                        <div class="stat-label">{{ __('Expenses this month') }}</div>
                        <div class="stat-value">{{ inr($expenses['month']) }}</div>
                        <div class="stat-meta">{{ __('Last month') }} {{ inr($expenses['last_month']) }}</div>
                    </div>
                </a>
            </div>
            @can('vendors.view')
                <div class="col-sm-6 col-xl-3">
                    <a href="{{ route('vendors.index') }}" class="card stat-card h-100 text-decoration-none">
                        <div class="card-body">
                            <div class="stat-icon"><i class="bi bi-shop"></i></div>
                            <div class="stat-label">{{ __('Vendor payable') }}</div>
                            <div class="stat-value">{{ inr($data['vendor_payable']) }}</div>
                            <div class="stat-meta">{{ __('Credit purchases not yet paid') }}</div>
                        </div>
                    </a>
                </div>
            @endcan
        @endif
        @if ($canMilk)
            <div class="col-sm-6 col-xl-3">
                <a href="{{ route('milk-alerts.index') }}" class="card stat-card h-100 text-decoration-none">
                    <div class="card-body">
                        <div class="stat-icon {{ $alerts['count'] ? 'is-warning' : '' }}"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-label">{{ __('Milk drop alerts') }}</div>
                        <div class="stat-value">{{ $alerts['count'] }}</div>
                        <div class="stat-meta">{{ __('Animals below their usual yield today') }}</div>
                    </div>
                </a>
            </div>
        @endif
    </div>

    {{-- Milk charts --}}
    @if ($canMilk || $canHerd)
        <div class="row g-3 mb-3">
            @if ($canMilk)
                <div class="{{ $canHerd ? 'col-lg-8' : 'col-12' }}">
                    <div class="card h-100">
                        <div class="card-header chart-head">
                            <div>
                                <div class="chart-title"><i class="bi bi-graph-up me-1"></i>{{ __('Milk production') }}</div>
                                <div class="chart-sub">{{ __('Last :n days, cow and buffalo', ['n' => \App\Services\DashboardService::CHART_DAYS]) }}</div>
                            </div>
                            <div class="chart-figures">
                                <div><span>{{ __('Total') }}</span><strong>{{ fmt_qty($production['chart']['total']) }} L</strong></div>
                                <div><span>{{ __('Daily avg') }}</span><strong>{{ fmt_qty($production['chart']['average']) }} L</strong></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-box">
                                <canvas id="chart-production" role="img" aria-label="{{ __('Milk production per day') }}"
                                        data-chart='@json($production['chart'])'
                                        data-label-cow="{{ __('Cow') }}" data-label-buffalo="{{ __('Buffalo') }}"
                                        data-label-total="{{ __('Total') }}" data-label-average="{{ __('Daily avg') }}"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            @if ($canHerd)
                <div class="{{ $canMilk ? 'col-lg-4' : 'col-12' }}">
                    <div class="card h-100">
                        <div class="card-header chart-head">
                            <div>
                                <div class="chart-title"><i class="bi bi-pie-chart me-1"></i>{{ __('Herd by stage') }}</div>
                                <div class="chart-sub">{{ __('Active animals') }}</div>
                            </div>
                        </div>
                        <div class="card-body">
                            @if ($herd['stages'])
                                <div class="chart-donut">
                                    <canvas id="chart-herd" role="img" aria-label="{{ __('Active animals by stage') }}"
                                            data-chart='@json($herd['stages'])' data-value="count"></canvas>
                                    <div class="chart-donut-center"><strong>{{ $herd['active'] }}</strong><span>{{ __('animals') }}</span></div>
                                </div>
                                <ul class="chart-legend" data-legend-for="chart-herd"></ul>
                            @else
                                <p class="text-body-secondary text-center my-5">{{ __('No active animals yet.') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Expense charts --}}
    @if ($canMoney)
        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    @php($finance = $data['finance'])
                    <div class="card-header chart-head">
                        <div>
                            <div class="chart-title"><i class="bi bi-bar-chart me-1"></i>{{ __('Income vs Expenses') }}</div>
                            <div class="chart-sub">{{ __('Last :n months', ['n' => \App\Services\DashboardService::CHART_MONTHS]) }}</div>
                        </div>
                        <div class="chart-figures">
                            <div><span>{{ __('Income (est.)') }}</span><strong class="text-success">{{ inr($finance['total_income']) }}</strong></div>
                            <div><span>{{ __('Expenses') }}</span><strong>{{ inr($finance['total_expenses']) }}</strong></div>
                            <div><span>{{ __('Profit') }}</span><strong class="{{ str_starts_with($finance['total_profit'], '-') ? 'text-danger' : 'text-success' }}">{{ inr($finance['total_profit']) }}</strong></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-box">
                            <canvas id="chart-finance" role="img" aria-label="{{ __('Income and expenses per month') }}"
                                    data-chart='@json($finance)' data-label-income="{{ __('Income (est.)') }}"
                                    data-label-expenses="{{ __('Expenses') }}" data-label-profit="{{ __('Profit') }}"></canvas>
                        </div>
                        <p class="chart-note mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ __('Income is estimated until milk sales are recorded: own milk produced × today\'s default rate') }}
                            ({{ collect($finance['rates'])->map(fn ($rate, $type) => \App\Enums\MilkType::from($type)->label().' '.inr($rate))->implode(', ') ?: __('no active products') }}).
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header chart-head">
                        <div>
                            <div class="chart-title"><i class="bi bi-tags me-1"></i>{{ __('Where the money went') }}</div>
                            <div class="chart-sub">{{ __('This month by category') }}</div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($expenses['categories'])
                            <div class="chart-donut">
                                <canvas id="chart-categories" role="img" aria-label="{{ __('Expenses by category') }}"
                                        data-chart='@json($expenses['categories'])' data-value="amount" data-money="1"></canvas>
                                <div class="chart-donut-center"><strong data-short-money="{{ $expenses['month'] }}" title="{{ inr($expenses['month']) }}">{{ inr($expenses['month']) }}</strong><span>{{ __('this month') }}</span></div>
                            </div>
                            <ul class="chart-legend" data-legend-for="chart-categories"></ul>
                        @else
                            <p class="text-body-secondary text-center my-5">{{ __('No expenses this month.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Stock and alerts tables --}}
    @if ($canStock || $canMilk)
        <div class="row g-3">
            @if ($canStock)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><i class="bi bi-box-seam me-1"></i>{{ __('Current milk stock') }}</div>
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Milk') }}</th><th class="text-end">{{ __('Litres') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($stock['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['source'] }}</td>
                                            <td>{{ $row['type'] }}</td>
                                            <td class="text-end {{ str_starts_with($row['litres'], '-') ? 'text-danger' : '' }}">{{ fmt_qty($row['litres']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr><td colspan="2">{{ __('Total') }}</td><td class="text-end">{{ fmt_qty($stock['total']) }}</td></tr></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
            @if ($canMilk)
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Milk drop alerts today') }}</span>
                            <a href="{{ route('milk-alerts.index') }}" class="small">{{ __('View all') }}</a>
                        </div>
                        @if ($alerts['top'])
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead><tr><th>{{ __('Animal') }}</th><th>{{ __('Shift') }}</th><th class="text-end">{{ __('Today') }}</th><th class="text-end">{{ __('Average') }}</th><th class="text-end">{{ __('Drop') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($alerts['top'] as $alert)
                                            <tr>
                                                <td>@if ($canHerd)<a href="{{ route('animals.show', $alert['animal_id']) }}">{{ $alert['animal'] }}</a>@else{{ $alert['animal'] }}@endif</td>
                                                <td>{{ $alert['shift'] }}</td>
                                                <td class="text-end">{{ fmt_qty($alert['today']) }}</td>
                                                <td class="text-end">{{ fmt_qty($alert['average']) }}</td>
                                                <td class="text-end text-danger">{{ $alert['drop'] }}%</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="card-body text-center text-body-secondary py-5">
                                <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>{{ __('No drops today. All animals are milking normally.') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset_v('vendor/chartjs/chart.umd.min.js') }}"></script>
    <script src="{{ asset_v('js/modules/dashboard-charts.js') }}"></script>
@endpush
