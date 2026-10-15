@extends('layouts.app')

@section('title', __('Audit log'))

@php
    $eventBadges = ['created' => 'text-bg-success', 'updated' => 'text-bg-primary', 'deleted' => 'text-bg-danger'];
@endphp

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 align-items-end" data-allow-resubmit>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="user" class="form-label small mb-1">{{ __('User') }}</label>
                <select id="user" name="user" class="form-select form-select-sm">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label for="model" class="form-label small mb-1">{{ __('Record type') }}</label>
                <select id="model" name="model" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($models as $type => $label)
                        <option value="{{ $type }}" @selected(($filters['model'] ?? '') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="event" class="form-label small mb-1">{{ __('Action') }}</label>
                <select id="event" name="event" class="form-select form-select-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach (['created' => __('Created'), 'updated' => __('Updated'), 'deleted' => __('Deleted')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['event'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                <input type="text" id="from" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="form-control form-control-sm js-date @error('from') is-invalid @enderror" placeholder="DD-MM-YYYY">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                <input type="text" id="to" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="form-control form-control-sm js-date @error('to') is-invalid @enderror" placeholder="DD-MM-YYYY">
            </div>
            <div class="col-12 col-xl-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-link btn-sm">{{ __('Clear') }}</a>
            </div>
        </form>
        @if ($errors->any())
            <div class="text-danger small mt-2">{{ $errors->first() }}</div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-nowrap">{{ __('Date & time') }}</th>
                    <th>{{ __('User') }}</th>
                    <th>{{ __('Action') }}</th>
                    <th>{{ __('Record') }}</th>
                    <th>{{ __('Changes (old → new)') }}</th>
                    <th>{{ __('Reason') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr>
                        <td class="small text-nowrap">{{ fmt_datetime($activity->created_at) }}</td>
                        <td>{{ $activity->causer?->name ?? __('System') }}</td>
                        <td>
                            <span class="badge {{ $eventBadges[$activity->event] ?? 'text-bg-secondary' }}">{{ __(ucfirst($activity->event ?? $activity->description)) }}</span>
                        </td>
                        <td>
                            {{ audit_model_label($activity->subject_type) }}
                            <span class="text-body-secondary">#{{ $activity->subject_id }}</span>
                            @if ($activity->subject?->getAttribute('name'))
                                <div class="small">{{ $activity->subject->getAttribute('name') }}</div>
                            @endif
                        </td>
                        <td class="small">
                            @forelse ($activity->diffRows() as [$field, $old, $new])
                                <div>
                                    <span class="fw-semibold">{{ str_replace('_', ' ', $field) }}:</span>
                                    @if ($activity->event !== 'created')
                                        <span class="text-danger text-decoration-line-through">{{ audit_value($old) }}</span>
                                        @if ($activity->event !== 'deleted') <i class="bi bi-arrow-right"></i> @endif
                                    @endif
                                    @if ($activity->event !== 'deleted')
                                        <span class="text-success">{{ audit_value($new) }}</span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-body-secondary">—</span>
                            @endforelse
                        </td>
                        <td class="small">{{ $activity->reason() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-journal-text fs-2 d-block mb-2"></i>
                            {{ __('No changes recorded for these filters.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $activities->links() }}</div>
@endsection
