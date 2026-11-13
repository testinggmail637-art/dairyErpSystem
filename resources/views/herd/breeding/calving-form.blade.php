@extends('layouts.app')

@section('title', __('Record calving'))

@section('page-actions')
    <a href="{{ route('breeding.calvings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Calvings') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <form method="POST" action="{{ route('breeding.calvings.store') }}" autocomplete="off">
            @csrf
            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <x-form.select name="mother_id" :label="__('Mother')" :options="$motherOptions" :selected="old('mother_id', $motherId)"
                                   :placeholder="__('Search tag or name')" select2 :data-ajax-url="route('animals.search', ['gender' => 'female', 'active' => 1])" required />
                </div>
                <div class="col-12 col-md-4">
                    <x-form.date name="calving_date" :label="__('Calving date')" :value="today()" max="today" required />
                </div>
            </div>
            <x-form.select name="outcome" :label="__('Outcome')" :options="$outcomes" :selected="old('outcome', 'alive')" required
                           :help="__('The mother becomes milking and starts her next lactation in every case.')" />

            <fieldset class="border rounded p-3 mb-3">
                <legend class="fs-6 w-auto px-1 mb-0">{{ __('Calf (when alive)') }}</legend>
                <div class="row g-2">
                    <div class="col-6 col-md-4"><x-form.select name="calf_gender" :label="__('Gender')" :options="$genders" :placeholder="__('Choose')" /></div>
                    <div class="col-6 col-md-4"><x-form.input name="calf_tag_no" :label="__('New tag no.')" maxlength="30" /></div>
                    <div class="col-12 col-md-4"><x-form.input name="calf_name" :label="__('Name')" maxlength="60" /></div>
                </div>
                <div class="form-text">{{ __('The calf is added to the herd: born on farm, this mother, her type and breed, stage calf.') }}</div>
            </fieldset>

            <x-form.textarea name="notes" :label="__('Notes')" rows="2" maxlength="500" />
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save calving') }}</button>
        </form>
    </div>
</div>
@endsection
