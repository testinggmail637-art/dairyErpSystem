@extends('layouts.app')

@section('title', __('Animals'))

@section('page-actions')
    @if ($canManage)
        <a href="{{ route('animals.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add animal') }}</a>
    @endif
@endsection

@section('content')
{{-- Active herd at a glance (one GROUP BY query) --}}
<div class="row g-2 mb-3">
    @foreach (\App\Enums\AnimalType::cases() as $type)
        @php($counts = $stageCounts[$type->value] ?? [])
        <div class="col-12 col-md-6">
            <div class="card h-100">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold">{{ $type === \App\Enums\AnimalType::Cow ? __('Cows') : __('Buffaloes') }}</span>
                        <span class="small text-body-secondary">{{ __(':count active', ['count' => array_sum($counts)]) }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach (\App\Enums\LifecycleStage::cases() as $stage)
                            @if ($counts[$stage->value] ?? 0)
                                <a href="{{ route('animals.index', ['type' => $type->value, 'stage' => $stage->value]) }}"
                                   class="badge text-bg-light border text-decoration-none">{{ $stage->label() }}: {{ $counts[$stage->value] }}</a>
                            @endif
                        @endforeach
                        @if ($counts === [])<span class="small text-body-secondary">{{ __('None yet') }}</span>@endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('animals.index') }}" class="row g-2 align-items-end" data-allow-resubmit data-ajax-list="#animals-results">
            <div class="col-12 col-md-4 col-xl-3">
                <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" id="search" name="search" value="{{ $filters['search'] }}" maxlength="60"
                       class="form-control form-control-sm" placeholder="{{ __('Tag no. or name') }}">
            </div>
            <div class="col-6 col-md-2 col-xl-1">
                <x-form.select name="type" :label="__('Type')" :options="\App\Enums\AnimalType::options()" :selected="$filters['type']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="stage" :label="__('Stage')" :options="\App\Enums\LifecycleStage::options()" :selected="$filters['stage']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">{{ __('Active') }}</option>
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
                    @foreach (\App\Enums\AnimalStatus::cases() as $status)
                        @continue($status === \App\Enums\AnimalStatus::Active)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="ownership" :label="__('Ownership')" :options="\App\Enums\OwnershipType::options()" :selected="$filters['ownership']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="gender" :label="__('Gender')" :options="\App\Enums\AnimalGender::options()" :selected="$filters['gender']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <x-form.select name="breed_id" :label="__('Breed')" :options="$breeds" :selected="$filters['breed_id']"
                               :placeholder="__('All')" group-class="mb-0" class="form-select-sm" />
            </div>
            <div class="col-12 col-md-3 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('animals.index') }}" class="btn btn-link btn-sm" data-ajax-clear>{{ __('Clear') }}</a>
            </div>
        </form>
    </div>

    <div id="animals-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="app-col-photo"></th>
                    <th>{{ __('Tag / name') }}</th>
                    <th>{{ __('Type / breed') }}</th>
                    <th>{{ __('Age') }}</th>
                    <th>{{ __('Stage') }}</th>
                    <th>{{ __('Ownership') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($animals as $animal)
                    <tr>
                        <td>
                            @if ($animal->photo_path)
                                <img src="{{ $animal->photoUrl(true) }}" alt="" class="rounded app-animal-thumb" loading="lazy">
                            @else
                                <span class="app-animal-thumb d-inline-flex align-items-center justify-content-center rounded bg-body-tertiary text-body-secondary">
                                    <i class="bi bi-image"></i>
                                </span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('animals.show', $animal) }}" class="fw-semibold">{{ $animal->tag_no }}</a>
                            @if ($animal->name)<div class="small text-body-secondary">{{ $animal->name }}</div>@endif
                        </td>
                        <td>
                            {{ $animal->animal_type->label() }}
                            <span class="small text-body-secondary">· {{ $animal->gender->label() }}</span>
                            @if ($animal->breed)<div class="small text-body-secondary">{{ $animal->breed->name }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $animal->age() ?? '—' }}</td>
                        <td>
                            {{ $animal->lifecycle_stage->label() }}
                            @if ($animal->lactation_number)<div class="small text-body-secondary">{{ __('Lactation :n', ['n' => $animal->lactation_number]) }}</div>@endif
                        </td>
                        <td>{{ $animal->ownership_type->label() }}</td>
                        <td><span class="badge {{ $animal->status->badge() }}">{{ $animal->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-clipboard2-pulse fs-2 d-block mb-2"></i>
                            {{ array_filter($filters) ? __('No animals match these filters.') : __('No animals yet.') }}
                            @if ($canManage && ! array_filter($filters))
                                <div class="mt-2"><a href="{{ route('animals.create') }}" class="btn btn-primary btn-sm">{{ __('Add first animal') }}</a></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($animals->hasPages())<div class="card-footer">{{ $animals->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
