@extends('layouts.app')

@section('title', __('Vaccines'))

@section('content')
@include('herd.health._tabs')
<div class="row g-3">
    @if ($canManage)
        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-plus-lg me-1"></i>{{ __('Add vaccine') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('health.vaccine-types.store') }}" autocomplete="off">
                        @csrf
                        <x-form.input name="name" :label="__('Name')" maxlength="100" required />
                        <x-form.input name="interval_days" type="number" min="1" max="3650" :label="__('Repeat every (days)')"
                                      :help="__('As advised by your vet. Sets the next due date.')" />
                        <x-form.input name="notes" :label="__('Notes')" maxlength="500" />
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Add') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div @class(['col-12', 'col-xl-8' => $canManage])>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr><th>{{ __('Vaccine') }}</th><th>{{ __('Repeat every') }}</th><th class="text-end">{{ __('Given') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            <tr>
                                <td class="fw-semibold">{{ $type->name }}</td>
                                <td>{{ $type->interval_days ? __(':n days', ['n' => $type->interval_days]) : __('Not set') }}</td>
                                <td class="text-end">{{ $type->vaccinations_count }}</td>
                                <td><span @class(['badge', 'text-bg-success' => $type->is_active, 'text-bg-secondary' => ! $type->is_active])>{{ $type->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="small">{{ $type->notes }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($canManage)
                                        <a href="{{ route('health.vaccine-types.edit', $type) }}" class="btn btn-link btn-sm p-0" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                        @if ($type->vaccinations_count === 0)
                                            <x-confirm-form :action="route('health.vaccine-types.destroy', $type)" :message="__('Delete :name?', ['name' => $type->name])" />
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
