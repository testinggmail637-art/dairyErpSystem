@extends('layouts.app')

@section('title', __('Animal :tag', ['tag' => $animal->tag_no]))

@php($canManage = auth()->user()->can('animals.manage'))

@section('page-actions')
    <a href="{{ route('animals.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Animals') }}</a>
    @if ($canManage)
        <a href="{{ route('animals.edit', $animal) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
    @endif
@endsection

@section('content')
<div class="row g-3">
    {{-- Summary card --}}
    <div class="col-12 col-lg-4 col-xl-3">
        <div class="card">
            @if ($animal->photo_path)
                <a href="{{ $animal->photoUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $animal->photoUrl() }}" alt="{{ $animal->tag_no }}" class="card-img-top app-animal-photo">
                </a>
            @endif
            <div class="card-body">
                <h2 class="h4 mb-0">{{ $animal->tag_no }}</h2>
                @if ($animal->name)<div class="text-body-secondary">{{ $animal->name }}</div>@endif
                <div class="mt-2 d-flex flex-wrap gap-1">
                    <span class="badge {{ $animal->status->badge() }}">{{ $animal->status->label() }}</span>
                    <span class="badge text-bg-light border">{{ $animal->lifecycle_stage->label() }}</span>
                    <span class="badge text-bg-light border">{{ $animal->ownership_type->label() }}</span>
                </div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7">{{ $animal->animal_type->label() }} · {{ $animal->gender->label() }}</dd>
                    <dt class="col-5">{{ __('Breed') }}</dt><dd class="col-7">{{ $animal->breed?->name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Age') }}</dt><dd class="col-7">{{ $animal->age() ?? '—' }}</dd>
                    @if ($animal->gender === \App\Enums\AnimalGender::Female)
                        <dt class="col-5">{{ __('Lactation') }}</dt><dd class="col-7">{{ $animal->lactation_number }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8 col-xl-9">
        <ul class="nav nav-tabs" role="tablist">
            @foreach (['details' => __('Details'), 'milk' => __('Milk'), 'health' => __('Health'), 'breeding' => __('Breeding'),
                       'expenses' => __('Expenses'), 'history' => __('History')] as $key => $label)
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if ($loop->first) active @endif" id="tab-{{ $key }}" data-bs-toggle="tab"
                            data-bs-target="#pane-{{ $key }}" type="button" role="tab" aria-controls="pane-{{ $key }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                        {{ $label }}
                        @if ($key === 'expenses' && $expenseCount)<span class="badge text-bg-secondary ms-1">{{ $expenseCount }}</span>@endif
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content card border-top-0 rounded-top-0">
            {{-- Details --}}
            <div class="tab-pane fade show active card-body" id="pane-details" role="tabpanel" aria-labelledby="tab-details" tabindex="0">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Date of birth') }}</dt>
                    <dd class="col-sm-8">{{ $animal->date_of_birth ? fmt_date($animal->date_of_birth) : '—' }}
                        @if ($animal->dob_is_estimated)<span class="small text-body-secondary">({{ __('approximate') }})</span>@endif</dd>
                    <dt class="col-sm-4">{{ __('Came from') }}</dt><dd class="col-sm-8">{{ $animal->source->label() }}</dd>
                    @if ($animal->opening_value !== null)
                        <dt class="col-sm-4">{{ __('Value at go-live') }}</dt><dd class="col-sm-8"><x-money :value="$animal->opening_value" /></dd>
                    @endif
                    <dt class="col-sm-4">{{ __('Mother') }}</dt>
                    <dd class="col-sm-8">
                        @if ($animal->mother)
                            <a href="{{ route('animals.show', $animal->mother) }}">{{ $animal->mother->label() }}</a>
                        @else — @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('Sire') }}</dt><dd class="col-sm-8">{{ $animal->sire_info ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Colour / marks') }}</dt><dd class="col-sm-8">{{ $animal->color_marks ?: '—' }}</dd>
                    @if ($animal->status_changed_on)
                        <dt class="col-sm-4">{{ __('Status since') }}</dt><dd class="col-sm-8">{{ fmt_date($animal->status_changed_on) }}</dd>
                    @endif
                    <dt class="col-sm-4">{{ __('Notes') }}</dt><dd class="col-sm-8">{!! $animal->notes ? nl2br(e($animal->notes)) : '—' !!}</dd>
                    <dt class="col-sm-4">{{ __('Added') }}</dt>
                    <dd class="col-sm-8">{{ fmt_datetime($animal->created_at) }} · {{ $animal->creator?->name ?? '—' }}</dd>
                </dl>

                @if ($calves->isNotEmpty())
                    <h3 class="h6 mt-4">{{ __('Calves') }}</h3>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Gender') }}</th><th>{{ __('Born') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                                @foreach ($calves as $calf)
                                    <tr>
                                        <td><a href="{{ route('animals.show', $calf) }}">{{ $calf->label() }}</a></td>
                                        <td>{{ $calf->gender->label() }}</td>
                                        <td>{{ $calf->date_of_birth ? fmt_date($calf->date_of_birth) : '—' }}</td>
                                        <td>{{ $calf->lifecycle_stage->label() }}</td>
                                        <td><span class="badge {{ $calf->status->badge() }}">{{ $calf->status->label() }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Milk / health / breeding are filled by milk entry (Phase 2) and health & breeding (Phase 5) --}}
            <div class="tab-pane fade card-body text-body-secondary" id="pane-milk" role="tabpanel" aria-labelledby="tab-milk" tabindex="0">
                <i class="bi bi-droplet me-1"></i>{{ __('Morning and evening milk of this animal will show here once milk entry is in use.') }}
            </div>
            <div class="tab-pane fade card-body text-body-secondary" id="pane-health" role="tabpanel" aria-labelledby="tab-health" tabindex="0">
                <i class="bi bi-heart-pulse me-1"></i>{{ __('Treatments and vaccinations of this animal will show here once health records are in use.') }}
            </div>
            <div class="tab-pane fade card-body text-body-secondary" id="pane-breeding" role="tabpanel" aria-labelledby="tab-breeding" tabindex="0">
                <i class="bi bi-gender-ambiguous me-1"></i>{{ __('Heat, AI, pregnancy checks and calvings will show here once breeding records are in use.') }}
            </div>

            {{-- Expenses linked to this animal --}}
            <div class="tab-pane fade" id="pane-expenses" role="tabpanel" aria-labelledby="tab-expenses" tabindex="0">
                <div class="card-body border-bottom small">
                    {{ trans_choice(':count expense|:count expenses', $expenseCount) }} · {{ __('Total') }}: <x-money :value="$expenseTotal" />
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @forelse ($recentExpenses as $expense)
                                <tr>
                                    <td>
                                        @can('view', $expense)<a href="{{ route('expenses.show', $expense) }}">{{ fmt_date($expense->expense_date) }}</a>
                                        @else {{ fmt_date($expense->expense_date) }} @endcan
                                    </td>
                                    <td>{{ $expense->category?->name }}@if ($expense->subcategory) › {{ $expense->subcategory->name }}@endif</td>
                                    <td class="text-end"><x-money :value="$expense->amount" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No expenses for this animal yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($expenseCount > $recentExpenses->count())
                    <div class="card-body small text-body-secondary">{{ __('Showing the latest :n.', ['n' => $recentExpenses->count()]) }}</div>
                @endif
            </div>

            {{-- History: every change from the audit log --}}
            <div class="tab-pane fade" id="pane-history" role="tabpanel" aria-labelledby="tab-history" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('What changed') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($history as $activity)
                                <tr>
                                    <td class="text-nowrap small">{{ fmt_datetime($activity->created_at) }}</td>
                                    <td class="small">{{ $activity->causer?->name ?? '—' }}</td>
                                    <td class="small">
                                        @if ($activity->event === 'created')
                                            {{ __('Animal added') }}
                                        @else
                                            @forelse ($activity->diffRows() as [$field, $old, $new])
                                                <div><span class="fw-semibold">{{ str_replace('_', ' ', $field) }}:</span>
                                                    {{ audit_value($old) }} <i class="bi bi-arrow-right"></i> {{ audit_value($new) }}</div>
                                            @empty — @endforelse
                                        @endif
                                    </td>
                                    <td class="small">{{ $activity->reason() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No changes recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="mt-3 text-end">
                <x-confirm-form :action="route('animals.destroy', $animal)" reason :label="__('Delete animal')"
                                :message="__('Delete animal :tag? This is only possible while it has no records.', ['tag' => $animal->tag_no])" />
            </div>
        @endif
    </div>
</div>
@endsection
