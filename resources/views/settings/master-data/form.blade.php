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

                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $row->name) }}" maxlength="100"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label">{{ __('Code') }} <span class="text-danger">*</span></label>
                        <input type="text" id="code" name="code" value="{{ old('code', $row->code) }}" maxlength="20"
                               class="form-control text-uppercase app-input-narrow @error('code') is-invalid @enderror" required>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('Short unique code, e.g. UPI or HERD.') }}</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                               @checked(old('is_active', $row->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                    </div>

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
