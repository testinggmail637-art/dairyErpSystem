@extends('layouts.app')

@section('title', __('Attendance'))

@php($statuses = \App\Enums\AttendanceStatus::cases())

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('attendance.index') }}" class="row g-2 align-items-end" id="attendance-date-form" data-allow-resubmit>
            <div class="col-8 col-md-3">
                <x-form.date name="date" :label="__('Date')" :value="$date" max="today" group-class="mb-0" required />
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">{{ __('Open') }}</button>
            </div>
            <div class="col-12 col-md-7 text-md-end small text-body-secondary">
                {{ __('Active staff employed on this day. Leave a row on "—" if not marked.') }}
            </div>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('attendance.store') }}" id="attendance-form">
    @csrf
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="fw-semibold">{{ fmt_date($date) }} · {{ trans_choice(':count staff member|:count staff members', $staff->count()) }}</span>
            @if ($staff->isNotEmpty())
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm" data-mark-all="present"><i class="bi bi-check2-all me-1"></i>{{ __('All present') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-mark-all="holiday">{{ __('All holiday') }}</button>
                </div>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Staff member') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        @php($row = $rows->get($member->id))
                        @php($current = old("attendance.{$member->id}.status", $row?->status?->value))
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $member->name }}</span>
                                <div class="small text-body-secondary">{{ $member->designation }}</div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ __('Status of :name', ['name' => $member->name]) }}">
                                    <input type="radio" class="btn-check" name="attendance[{{ $member->id }}][status]" id="att-{{ $member->id }}-none" value="" @checked($current === null || $current === '')>
                                    <label class="btn btn-outline-secondary" for="att-{{ $member->id }}-none" title="{{ __('Not marked') }}">—</label>
                                    @foreach ($statuses as $status)
                                        <input type="radio" class="btn-check" name="attendance[{{ $member->id }}][status]" id="att-{{ $member->id }}-{{ $status->value }}"
                                               value="{{ $status->value }}" data-status="{{ $status->value }}" @checked($current === $status->value)>
                                        <label class="btn {{ match ($status) {
                                            \App\Enums\AttendanceStatus::Present => 'btn-outline-success',
                                            \App\Enums\AttendanceStatus::Absent => 'btn-outline-danger',
                                            \App\Enums\AttendanceStatus::HalfDay, \App\Enums\AttendanceStatus::Leave => 'btn-outline-warning',
                                            default => 'btn-outline-primary',
                                        } }}" for="att-{{ $member->id }}-{{ $status->value }}" title="{{ $status->label() }}">{{ $status->label() }}</label>
                                    @endforeach
                                </div>
                                @error("attendance.{$member->id}.status")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </td>
                            <td style="min-width: 12rem">
                                <input type="text" name="attendance[{{ $member->id }}][note]" value="{{ old("attendance.{$member->id}.note", $row?->note) }}"
                                       class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Note for :name', ['name' => $member->name]) }}">
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-5">
                                <i class="bi bi-people fs-2 d-block mb-2"></i>{{ __('No active staff on this date.') }}
                                @can('staff.manage')<div class="mt-2"><a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm">{{ __('Add staff member') }}</a></div>@endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->isNotEmpty())
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save attendance') }}</button>
            </div>
        @endif
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/attendance.js') }}"></script>
@endpush
