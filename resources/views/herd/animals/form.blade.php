@extends('layouts.app')

@section('title', $animal->exists ? __('Edit animal :tag', ['tag' => $animal->tag_no]) : __('Add animal'))

@section('page-actions')
    <a href="{{ $animal->exists ? route('animals.show', $animal) : route('animals.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>{{ $animal->exists ? __('Back to profile') : __('Back to animals') }}
    </a>
@endsection

@section('content')
<form method="POST" action="{{ $animal->exists ? route('animals.update', $animal) : route('animals.store') }}"
      enctype="multipart/form-data" id="animal-form">
    @csrf
    @if ($animal->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-tag me-1"></i>{{ __('Identity') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <x-form.input name="tag_no" :label="__('Tag no.')" :value="$animal->tag_no" maxlength="30" required
                                          autofocus group-class="mb-0" :help="__('Unique in this farm, e.g. C-101.')" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.input name="name" :label="__('Name')" :value="$animal->name" maxlength="60" group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="animal_type" :label="__('Type')" :options="\App\Enums\AnimalType::options()"
                                           :selected="$animal->animal_type?->value" required group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="breed_id" :label="__('Breed')" :options="$breeds" :selected="$animal->breed_id"
                                           :placeholder="__('— Not known —')" group-class="mb-0"
                                           data-children-of="#animal_type" :data-children-url="route('animal-breeds.by-type', '__ID__')" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="gender" :label="__('Gender')" :options="\App\Enums\AnimalGender::options()"
                                           :selected="$animal->gender?->value" required group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.input name="color_marks" :label="__('Colour / marks')" :value="$animal->color_marks" maxlength="100" group-class="mb-0" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header fw-semibold"><i class="bi bi-heart-pulse me-1"></i>{{ __('Stage & ownership') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <x-form.select name="lifecycle_stage" :label="__('Stage')" :options="\App\Enums\LifecycleStage::options()"
                                           :selected="$animal->lifecycle_stage?->value" required group-class="mb-0"
                                           :help="__('Only milking females appear in milk entry.')" />
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="ownership_type" :label="__('Ownership')" :options="\App\Enums\OwnershipType::options()"
                                           :selected="$animal->ownership_type?->value" required group-class="mb-0" />
                        </div>
                        <div class="col-6 col-md-4" data-show-when="female">
                            <x-form.input name="lactation_number" type="number" min="0" max="30" :label="__('Lactation no.')"
                                          :value="$animal->lactation_number" group-class="mb-0" :help="__('Number of calvings so far.')" />
                        </div>
                        @if ($animal->exists)
                            <div class="col-12">
                                <span class="small text-body-secondary">{{ __('Status') }}:</span>
                                <span class="badge {{ $animal->status->badge() }}">{{ $animal->status->label() }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header fw-semibold"><i class="bi bi-diagram-2 me-1"></i>{{ __('Birth & origin') }}</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <x-form.date name="date_of_birth" :label="__('Date of birth')" :value="$animal->date_of_birth" max="today" group-class="mb-0" />
                            <div class="form-check mt-1">
                                <input type="hidden" name="dob_is_estimated" value="0">
                                <input class="form-check-input" type="checkbox" name="dob_is_estimated" value="1" id="dob_is_estimated"
                                       @checked(old('dob_is_estimated', $animal->dob_is_estimated))>
                                <label class="form-check-label small" for="dob_is_estimated">{{ __('Approximate date') }}</label>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <x-form.select name="source" :label="__('Came from')" :options="\App\Enums\AnimalSource::options()"
                                           :selected="$animal->source?->value" required group-class="mb-0" />
                        </div>
                        <div class="col-12 col-md-4" data-show-when="opening">
                            <x-form.money name="opening_value" :label="__('Value at go-live')" :value="$animal->opening_value" group-class="mb-0"
                                          :help="__('Only for animals already on the farm when you started using the app.')" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.select name="mother_id" :label="__('Mother')" :options="$mother" :selected="$animal->mother_id"
                                           :placeholder="__('Search by tag or name')" select2 group-class="mb-0"
                                           :data-ajax-url="route('animals.search', array_filter(['gender' => 'female', 'except' => $animal->id]))"
                                           data-ajax-with="#animal_type" />
                        </div>
                        <div class="col-12 col-md-6">
                            <x-form.input name="sire_info" :label="__('Sire (bull / semen)')" :value="$animal->sire_info" maxlength="150" group-class="mb-0" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header fw-semibold"><i class="bi bi-camera me-1"></i>{{ __('Photo') }}</div>
                <div class="card-body">
                    @if ($animal->photo_path)
                        <img src="{{ $animal->photoUrl(true) }}" alt="{{ $animal->tag_no }}" class="rounded mb-2 app-animal-photo-sm">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                            <label class="form-check-label small" for="remove_photo">{{ __('Remove the photo') }}</label>
                        </div>
                    @endif
                    <x-form.input name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"
                                  :label="$animal->photo_path ? __('Replace with') : null"
                                  :help="__('JPG, PNG or WebP, up to 5 MB. Phone photos are resized automatically.')" group-class="mb-0" />
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <x-form.textarea name="notes" :label="__('Notes')" :value="$animal->notes" rows="4" maxlength="2000" group-class="mb-0" />
                </div>
            </div>
        </div>
    </div>

    @if ($animal->exists)
        <div class="card mt-3">
            <div class="card-body pb-0">
                <x-form.reason :placeholder="__('e.g. Wrong date of birth entered earlier')" />
            </div>
        </div>
    @endif

    <div class="mt-3 d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        @unless ($animal->exists)
            <button type="submit" name="add_another" value="1" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Save & add another') }}</button>
        @endunless
        <a href="{{ $animal->exists ? route('animals.show', $animal) : route('animals.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection

@push('scripts')
    <script src="{{ asset_v('js/modules/animal-form.js') }}"></script>
@endpush
