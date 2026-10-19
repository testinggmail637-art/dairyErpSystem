@extends('layouts.app')

@section('title', $breed->exists ? __('Edit breed') : __('Add breed'))

@section('page-actions')
    <a href="{{ route('animal-breeds.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Back to breeds') }}
    </a>
@endsection

@section('content')
<div class="row">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $breed->exists ? route('animal-breeds.update', $breed) : route('animal-breeds.store') }}">
                    @csrf
                    @if ($breed->exists) @method('PUT') @endif

                    <x-form.select name="animal_type" :label="__('Animal type')" :options="\App\Enums\AnimalType::options()"
                                   :selected="$breed->animal_type?->value" required />

                    <x-form.input name="name" :label="__('Breed name')" :value="$breed->name" maxlength="60" required
                                  :help="__('E.g. Sahiwal, Murrah. Must be unique for the animal type.')" />

                    <x-form.switch name="is_active" :label="__('Active')" :checked="$breed->is_active"
                                   :help="__('Inactive breeds are not offered for new animals; animals that have it keep it.')" />

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
                        <a href="{{ route('animal-breeds.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
