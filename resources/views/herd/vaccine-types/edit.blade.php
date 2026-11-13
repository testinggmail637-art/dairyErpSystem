@extends('layouts.app')

@section('title', __('Edit vaccine'))

@section('page-actions')
    <a href="{{ route('health.vaccine-types.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Vaccines') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 560px;">
    <div class="card-body">
        <form method="POST" action="{{ route('health.vaccine-types.update', $type) }}" autocomplete="off">
            @csrf
            @method('PUT')
            <x-form.input name="name" :label="__('Name')" :value="$type->name" maxlength="100" required />
            <x-form.input name="interval_days" type="number" min="1" max="3650" :label="__('Repeat every (days)')" :value="$type->interval_days"
                          :help="__('Changes the default next due date of new vaccinations only.')" />
            <x-form.input name="notes" :label="__('Notes')" :value="$type->notes" maxlength="500" />
            <x-form.switch name="is_active" :label="__('Active')" :checked="$type->is_active" :help="__('Inactive vaccines are not offered for new vaccinations.')" />
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        </form>
    </div>
</div>
@endsection
