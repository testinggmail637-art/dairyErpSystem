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
@endsection
