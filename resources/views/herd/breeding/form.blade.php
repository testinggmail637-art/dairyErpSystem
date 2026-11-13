@extends('layouts.app')

@section('title', $event->exists ? __('Edit breeding event') : __('Add breeding event'))

@section('page-actions')
    <a href="{{ route('breeding.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Breeding') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <form method="POST" action="{{ $event->exists ? route('breeding.update', $event) : route('breeding.store') }}" autocomplete="off">
            @csrf
            @if ($event->exists) @method('PUT') @endif

            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <x-form.select name="animal_id" :label="__('Animal')" :options="$animalOptions" :selected="old('animal_id', $event->animal_id)"
                                   :placeholder="__('Search tag or name')" select2 :data-ajax-url="route('animals.search', ['gender' => 'female', 'active' => 1])" required />
                </div>
                <div class="col-12 col-md-4">
                    <x-form.date name="event_date" :label="__('Date')" :value="$event->event_date" max="today" required />
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <x-form.select name="event_type" :label="__('Event')" :options="$types" :selected="old('event_type', $event->event_type?->value)" required
                                   :help="__('AI / natural service: expected calving = date + gestation days (Settings).')" />
                </div>
                <div class="col-12 col-md-6">
                    <x-form.select name="result" :label="__('Pregnancy check result')" :options="\App\Enums\PregnancyResult::options()"
                                   :selected="old('result', $event->result?->value)" :placeholder="__('Only for a pregnancy check')" />
                </div>
            </div>
            <div class="row g-2">
                <div class="col-12 col-md-6"><x-form.input name="sire_info" :label="__('Bull / semen straw')" :value="$event->sire_info" maxlength="150" /></div>
                <div class="col-12 col-md-6"><x-form.input name="technician" :label="__('Technician / vet')" :value="$event->technician" maxlength="150" /></div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-3"><x-form.money name="cost" :label="__('Cost')" :value="$event->cost" /></div>
                <div class="col-6 col-md-5">
                    <x-form.switch name="record_as_expense" :label="__('Also record as expense (Health → AI / Breeding)')" :checked="old('record_as_expense', $recordAsExpense)" />
                </div>
                <div class="col-12 col-md-4">
                    <x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :selected="old('payment_mode_id', $paymentModeId)" :placeholder="__('Choose')" />
                </div>
            </div>
            <x-form.textarea name="notes" :label="__('Notes')" :value="$event->notes" rows="2" maxlength="500" />
            @if ($event->exists)
                <x-form.reason />
            @endif

            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save') }}</button>
        </form>
    </div>
</div>
@endsection
