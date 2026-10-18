@extends('layouts.app')

@section('title', __('Check import'))

@section('page-actions')
    <a href="{{ route('expense-imports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}</a>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="me-auto">
            <div class="fw-semibold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>{{ $batch->original_filename }}</div>
            <div class="small text-body-secondary">
                {{ __('Uploaded :date by :user', ['date' => fmt_datetime($batch->created_at), 'user' => $batch->creator?->name ?? '—']) }}
                · <span class="badge {{ $batch->status->badge() }}">{{ $batch->status->label() }}</span>
            </div>
        </div>
        <div class="text-center px-2"><div class="fs-4 fw-semibold">{{ number_format($batch->total_rows) }}</div><div class="small text-body-secondary">{{ __('Rows') }}</div></div>
        <div class="text-center px-2 text-success"><div class="fs-4 fw-semibold">{{ number_format($batch->valid_rows) }}</div><div class="small">{{ __('Ready') }}</div></div>
        <div class="text-center px-2 text-danger"><div class="fs-4 fw-semibold">{{ number_format($batch->error_rows) }}</div><div class="small">{{ __('With errors') }}</div></div>
        <div class="text-center px-2 text-warning-emphasis"><div class="fs-4 fw-semibold">{{ number_format($batch->warning_rows) }}</div><div class="small">{{ __('Possible duplicates') }}</div></div>
    </div>
    @if ($batch->isPreviewed())
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-body-secondary me-auto">
                {{ __('Rows with errors are skipped. Possible duplicates are imported unless you remove them from the file and upload again.') }}
            </span>
            <x-confirm-form :action="route('expense-imports.cancel', $batch)" method="POST" :label="__('Cancel upload')" icon="bi-x-lg"
                            button-class="btn btn-outline-secondary btn-sm" :message="__('Cancel this upload? Nothing will be imported.')" />
        </div>
    @endif
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('expense-imports.show', $batch) }}" class="d-flex flex-wrap gap-2 align-items-center" data-allow-resubmit data-ajax-list="#import-rows">
            <label for="show" class="small">{{ __('Show') }}</label>
            <select id="show" name="show" class="form-select form-select-sm w-auto">
                <option value="">{{ __('All rows') }}</option>
                <option value="errors" @selected($show === 'errors')>{{ __('Rows with errors') }}</option>
                <option value="warnings" @selected($show === 'warnings')>{{ __('Possible duplicates') }}</option>
                <option value="valid" @selected($show === 'valid')>{{ __('Ready rows') }}</option>
            </select>
        </form>
    </div>

    <div id="import-rows" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle mb-0 import-preview">
            <thead>
                <tr>
                    <th class="text-end">{{ __('Row') }}</th>
                    @foreach ($columns as $header)<th class="text-nowrap">{{ $header }}</th>@endforeach
                    <th>{{ __('Check') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-end text-body-secondary">{{ $row->row_number }}</td>
                        @foreach ($columns as $key => $header)
                            @php($cellErrors = $row->errorsFor($key))
                            <td @class(['table-danger' => $cellErrors, 'text-nowrap' => $key !== 'note'])>
                                {{ $row->payload[$key] ?? '' }}
                                @foreach ($cellErrors as $message)
                                    <div class="small text-danger-emphasis">{{ $message }}</div>
                                @endforeach
                            </td>
                        @endforeach
                        <td class="small">
                            @if ($row->is_valid)
                                <span class="badge text-bg-success">{{ __('Ready') }}</span>
                            @else
                                <span class="badge text-bg-danger">{{ trans_choice(':count error|:count errors', count(array_merge(...array_values($row->errors ?? [])))) }}</span>
                            @endif
                            @foreach ($row->warnings ?? [] as $warning)
                                <div class="text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ $warning }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) + 2 }}" class="text-center text-body-secondary py-5">{{ __('No rows to show.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
