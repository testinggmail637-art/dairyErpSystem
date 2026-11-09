@extends('layouts.app')

@section('title', __('Farmers'))

@php($canManage = auth()->user()->can('farmers.manage'))
@php($filtered = array_filter($filters) !== [])

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('farmers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add farmer') }}</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('farmers.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#farmers-results">
            <div class="col-12 col-md-5">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" class="form-control form-control-sm"
                       placeholder="{{ __('Code, name, village or phone') }}">
            </div>
            <div class="col-6 col-md-2">
                <label for="milk_type" class="form-label small mb-1">{{ __('Milk') }}</label>
                <select id="milk_type" name="milk_type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\MilkType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['milk_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('farmers.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="farmers-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Farmer') }}</th>
                    <th>{{ __('Village') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Milk') }}</th>
                    <th>{{ __('Bank / UPI') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($farmers as $farmer)
                    <tr>
                        <td class="font-monospace small text-nowrap">{{ $farmer->code }}</td>
                        <td>
                            <a href="{{ route('farmers.show', $farmer) }}" class="fw-semibold">{{ $farmer->name }}</a>
                            @if ($farmer->father_name)<div class="small text-body-secondary">{{ __('s/o :name', ['name' => $farmer->father_name]) }}</div>@endif
                        </td>
                        <td>{{ $farmer->village ?: '—' }}</td>
                        <td class="text-nowrap">{{ $farmer->phone ?: '—' }}</td>
                        <td>{{ $farmer->default_milk_type->label() }}</td>
                        <td class="small">
                            @if ($farmer->bank_account_no)<i class="bi bi-bank me-1"></i>{{ __('Bank') }}@endif
                            @if ($farmer->upi_id)<i class="bi bi-phone ms-1 me-1"></i>{{ __('UPI') }}@endif
                            @if (! $farmer->bank_account_no && ! $farmer->upi_id)<span class="text-body-secondary">{{ __('Cash') }}</span>@endif
                        </td>
                        <td>
                            @if ($farmer->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('farmers.edit', $farmer) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-body-secondary py-5">
                            <i class="bi bi-person-badge fs-2 d-block mb-2"></i>
                            {{ $filtered ? __('No farmers match these filters.') : __('No farmers yet.') }}
                            @if ($canManage && ! $filtered)
                                <div class="mt-2"><a href="{{ route('farmers.create') }}" class="btn btn-primary btn-sm">{{ __('Add first farmer') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($farmers->hasPages())<div class="card-footer">{{ $farmers->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
