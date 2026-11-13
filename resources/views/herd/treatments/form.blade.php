@extends('layouts.app')

@section('title', $treatment->exists ? __('Edit treatment') : __('Add treatment'))

@section('page-actions')
    <a href="{{ route('health.treatments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('Treatments') }}</a>
@endsection

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-body">
        <form method="POST" action="{{ $treatment->exists ? route('health.treatments.update', $treatment) : route('health.treatments.store') }}" autocomplete="off">
            @csrf
            @if ($treatment->exists) @method('PUT') @endif

            <div class="row g-2">
                <div class="col-12 col-md-8">
                    <x-form.select name="animal_id" :label="__('Animal')" :options="$animalOptions" :selected="old('animal_id', $treatment->animal_id)"
                                   :placeholder="__('Search tag or name')" select2 :data-ajax-url="route('animals.search', ['active' => 1])" required />
                </div>
                <div class="col-12 col-md-4">
                    <x-form.date name="treatment_date" :label="__('Date')" :value="$treatment->treatment_date" max="today" required />
                </div>
            </div>
            <x-form.input name="diagnosis" :label="__('Diagnosis')" :value="$treatment->diagnosis" maxlength="255" required :placeholder="__('e.g. Mastitis, fever')" />
            <div class="row g-2">
                <div class="col-12 col-md-6"><x-form.input name="medicine" :label="__('Medicine')" :value="$treatment->medicine" maxlength="255" /></div>
                <div class="col-6 col-md-3"><x-form.input name="dose" :label="__('Dose')" :value="$treatment->dose" maxlength="100" /></div>
                <div class="col-6 col-md-3"><x-form.input name="vet_name" :label="__('Vet')" :value="$treatment->vet_name" maxlength="150" /></div>
            </div>
            <div class="row g-2">
                <div class="col-6 col-md-4"><x-form.money name="cost" :label="__('Cost')" :value="$treatment->cost" /></div>
                <div class="col-6 col-md-4">
                    <x-form.date name="milk_withdrawal_until" :label="__('No milk sale until')" :value="$treatment->milk_withdrawal_until"
                                 :help="__('Milk entry shows a warning for this animal until this date.')" />
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <x-form.switch name="record_as_expense" :label="__('Also record as expense (Health → Medicine)')" :checked="old('record_as_expense', $recordAsExpense)"
                                   :help="__('Off = the cost is only noted here. The only way a treatment cost reaches expenses.')" />
                </div>
                <div class="col-12 col-md-6">
                    <x-form.select name="payment_mode_id" :label="__('Paid by')" :options="$paymentModes" :selected="old('payment_mode_id', $paymentModeId)" :placeholder="__('Choose')" />
                </div>
            </div>
            <x-form.textarea name="notes" :label="__('Notes')" :value="$treatment->notes" rows="2" maxlength="1000" />
            @if ($treatment->exists)
                <x-form.reason />
            @endif

            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ __('Save treatment') }}</button>
        </form>
    </div>
</div>
@endsection
