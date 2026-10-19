@extends('layouts.app')

@section('title', __('Breeds'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('animal-breeds.create', array_filter(['type' => $filters['type']])) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add breed') }}
        </a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('animal-breeds.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#breeds-results">
            <div class="col-6 col-md-3">
                <label for="type" class="form-label small mb-1">{{ __('Animal type') }}</label>
                <select id="type" name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\AnimalType::options() as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
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
            <div class="col-12 col-md-4">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" maxlength="60"
                       class="form-control form-control-sm" placeholder="{{ __('Breed name') }}">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('animal-breeds.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="breeds-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Breed') }}</th>
                    <th>{{ __('Animal type') }}</th>
                    <th>{{ __('Status') }}</th>
                    @if ($canManage)<th class="text-end">{{ __('Actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($breeds as $breed)
                    <tr>
                        <td class="fw-semibold">{{ $breed->name }}</td>
                        <td>{{ $breed->animal_type->label() }}</td>
                        <td>
                            @if ($breed->is_active)
                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        @if ($canManage)
                            <td class="text-end">
                                <a href="{{ route('animal-breeds.edit', $breed) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <span class="d-none d-sm-inline">{{ __('Edit') }}</span>
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canManage ? 4 : 3 }}" class="text-center text-body-secondary py-5">
                            {{ array_filter($filters) ? __('No breeds match these filters.') : __('No breeds yet.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($breeds->hasPages())<div class="card-footer">{{ $breeds->links() }}</div>@endif
    @endfragment
    </div>
</div>
<p class="form-text mt-2">{{ __('Breeds are never deleted — make one inactive to stop offering it for new animals.') }}</p>
@endsection
