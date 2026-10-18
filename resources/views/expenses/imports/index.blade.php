@extends('layouts.app')

@section('title', __('Import expenses'))

@section('content')
<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header fw-semibold"><span class="badge text-bg-primary me-1">1</span>{{ __('Download the template') }}</div>
            <div class="card-body">
                <p>{{ __('Fill one expense per row in the "Expenses" sheet. The "Lists" sheet shows the categories, subcategories, sections, payment modes and vendors of this farm — names must match (capital letters do not matter).') }}</p>
                <a href="{{ route('expense-imports.template') }}" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>{{ __('Download expenses_template.xlsx') }}
                </a>
                <p class="form-text mt-2 mb-0">{{ __('Download a fresh template whenever you add categories or vendors, so its lists are up to date.') }}</p>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header fw-semibold"><span class="badge text-bg-primary me-1">2</span>{{ __('Columns') }}</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Column') }}</th><th>{{ __('What to enter') }}</th></tr></thead>
                    <tbody>
                        @foreach ($columns as $key => $header)
                            <tr>
                                <td class="text-nowrap"><code>{{ $header }}</code></td>
                                <td class="small">{{ $help[$key] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold"><span class="badge text-bg-primary me-1">3</span>{{ __('Upload and check') }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('expense-imports.store') }}" enctype="multipart/form-data" class="row g-2 align-items-start">
            @csrf
            <div class="col-12 col-md-8">
                <label for="file" class="form-label visually-hidden">{{ __('Filled file') }}</label>
                <input type="file" id="file" name="file" accept=".xlsx,.xls,.csv" required
                       class="form-control @error('file') is-invalid @enderror">
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ __('Up to 5,000 rows (xlsx, xls or csv, max 5 MB). Every row is checked like the expense form; you see errors and possible duplicates before anything is saved.') }}</div>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>{{ __('Upload and check') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header fw-semibold"><i class="bi bi-clock-history me-1"></i>{{ __('Import history') }}</div>
    <div id="import-history" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Uploaded') }}</th>
                    <th>{{ __('File') }}</th>
                    <th class="text-end">{{ __('Rows') }}</th>
                    <th class="text-end">{{ __('Imported') }}</th>
                    <th class="text-end">{{ __('Errors') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="text-nowrap">
                            {{ fmt_datetime($batch->created_at) }}
                            <div class="small text-body-secondary">{{ $batch->creator?->name ?? '—' }}</div>
                        </td>
                        <td class="text-break">{{ $batch->original_filename }}</td>
                        <td class="text-end">{{ number_format($batch->total_rows) }}</td>
                        <td class="text-end">{{ number_format($batch->imported_rows) }}</td>
                        <td class="text-end">{{ $batch->error_rows ? number_format($batch->error_rows) : '—' }}</td>
                        <td>
                            <span class="badge {{ $batch->status->badge() }}">{{ $batch->status->label() }}</span>
                            @if ($batch->reverted_at)
                                <div class="small text-body-secondary">{{ fmt_datetime($batch->reverted_at) }} · {{ $batch->reverter?->name ?? '—' }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('expense-imports.show', $batch) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> <span class="d-none d-sm-inline">{{ __('Open') }}</span>
                            </a>
                            @if ($batch->isImported())
                                <a href="{{ route('expenses.index', ['import_batch_id' => $batch->id]) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-list-ul"></i> <span class="d-none d-sm-inline">{{ __('Expenses') }}</span>
                                </a>
                                @if ($canRevert)
                                    <x-confirm-form :action="route('expense-imports.revert', $batch)" method="POST" reason icon="bi-arrow-counterclockwise"
                                                    :message="__('Revert this upload? Its :count expenses will be deleted.', ['count' => number_format($batch->imported_rows)])"
                                                    :confirm-button="__('Revert')" />
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-5">{{ __('No uploads yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($batches->hasPages())<div class="card-footer">{{ $batches->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
