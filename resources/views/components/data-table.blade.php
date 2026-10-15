{{--
    DataTable (initialised by public/js/app.js). Server-side when `url` is given (yajra/laravel-datatables JSON),
    otherwise it enhances the rows in the slot (search, sort, paging).

    <x-data-table id="expenses-table" :url="route('expenses.data')" :order="[[0, 'desc']]" :columns="[
        ['data' => 'date', 'title' => __('Date')],
        ['data' => 'amount', 'title' => __('Amount'), 'className' => 'text-end'],
        ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
    ]" />

    Client-side: <x-data-table id="t" :columns="[['title' => 'Name'], …]"> <tr><td>…</td></tr> … </x-data-table>
--}}
@props(['id', 'columns' => [], 'url' => null, 'order' => [], 'pageLength' => null])

<div class="table-responsive">
    <table id="{{ $id }}"
           {{ $attributes->class(['table', 'table-hover', 'align-middle', 'w-100', 'js-datatable']) }}
           @if ($url) data-url="{{ $url }}" @endif
           data-columns='@json(collect($columns)->map(fn ($c) => \Illuminate\Support\Arr::except($c, ['title']))->values())'
           @if ($order) data-order='@json($order)' @endif
           @if ($pageLength) data-page-length="{{ $pageLength }}" @endif>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ $column['className'] ?? '' }}">{{ $column['title'] ?? '' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
