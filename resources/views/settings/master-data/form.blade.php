@extends('layouts.app')

@section('title', $row->exists
    ? __('Edit :item', ['item' => strtolower($labels['singular'])])
    : __('Add :item', ['item' => strtolower($labels['singular'])]))

@section('page-actions')
    <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to :items', ['items' => strtolower($labels['plural'])]) }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $row->exists ? route($routePrefix.'.update', $row) : route($routePrefix.'.store') }}">
                    @csrf
                    @if ($row->exists) @method('PUT') @endif

                    <x-form.input name="name" :label="__('Name')" :value="$row->name" maxlength="100" required />

                    <x-form.input name="code" :label="__('Code')" :value="$row->code" maxlength="20" required
                                  class="text-uppercase app-input-narrow" :help="__('Short unique code, e.g. UPI or HERD.')" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$row->is_active" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
