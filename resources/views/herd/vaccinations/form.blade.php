@extends('layouts.app')

@section('title', $vaccination->exists ? __('Edit vaccination') : __('Give vaccine'))

@section('page-actions')
    <a href="{{ route('health.vaccinations.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Vaccinations') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <form method="POST" action="{{ $vaccination->exists ? route('health.vaccinations.update', $vaccination) : route('health.vaccinations.store') }}" autocomplete="off">
            @csrf
            @if ($vaccination->exists) @method('PUT') @endif

            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <x-form.select name="vaccine_type_id" :label="__('Vaccine')" :options="$vaccineTypes" :selected="old('vaccine_type_id', $vaccination->vaccine_type_id)"
                                   :placeholder="__('Choose')" required />
                </div>
                <div class="col-12 col-md-4">
                    <x-form.date name="given_on" :label="__('Given on')" :value="$vaccination->given_on" max="today" required />
                </div>
            </div>

            @if ($bulk)
                <x-form.select name="animal_ids[]" :label="__('Animals')" :options="$animalOptions" :selected="array_keys($animalOptions)" multiple
                               :placeholder="__('Search tag or name — pick one or many')" select2 :data-ajax-url="route('animals.search', ['active' => 1])" required
                               :help="__('One vaccination (and one expense, if recorded) is saved for each animal.')" />
            @else
                <x-form.select name="animal_ids[]" id="animal_id" :label="__('Animal')" :options="$animalOptions" :selected="array_keys($animalOptions)"
                               select2 :data-ajax-url="route('animals.search', ['active' => 1])" required />
            @endif

            <div class="row g-2">
                <div class="col-6 col-md-4">
                    <x-form.date name="next_due_on" :label="__('Next due')" :value="$vaccination->next_due_on"
                                 :help="__('Empty = date given + the vaccine\'s interval (if set).')" />
                </div>
                <div class="col-6 col-md-4"><x-form.money name="cost" :label="$bulk ? __('Cost per animal') : __('Cost')" :value="$vaccination->cost" /></div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <x-form.switch name="record_as_expense" :label="__('Also record as expense (Health → Vaccination)')" :checked="old('record_as_expense', $recordAsExpense)"
                                   :help="__('Off = the cost is only noted here.')" />
                </div>
                <div class="col-12 col-md-6">
                    <x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :selected="old('payment_mode_id', $paymentModeId)" :placeholder="__('Choose')" />
                </div>
            </div>
            <x-form.textarea name="notes" :label="__('Notes')" :value="$vaccination->notes" rows="2" maxlength="500" />
            @if ($vaccination->exists)
                <x-form.reason />
            @endif

            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        </form>
    </div>
</div>
@endsection
